<?php

namespace Tests\Feature\MemberServices;

use App\Enums\AdvertisingPeriodStatus;
use App\Enums\MemberServiceOrderStatus;
use App\Enums\MemberServicePackage;
use App\Models\AdvertisingPeriod;
use App\Models\MemberServiceOrder;
use App\Models\Property;
use App\Models\User;
use App\Services\Chargeback\EvidenceBundleService;
use App\Services\MemberServices\AdvertisingActivator;
use App\Services\MemberServices\MemberServiceOrderFactory;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Advertising periods: which property is advertised, for how long, from when.
 *
 * The order records that a member bought N weeks. Without this, the system
 * could say "they paid $1,796" and nothing else — not whether the ad is live,
 * when it expires, or how long they have left. It is also the fulfilment half
 * of a chargeback defence: a receipt proves a charge, this proves the service
 * was delivered.
 */
class AdvertisingPeriodTest extends TestCase
{
    use RefreshDatabase;

    private function paidOrder(MemberServicePackage $package, int $weeks, string $email = 'ada@example.com'): MemberServiceOrder
    {
        $order = app(MemberServiceOrderFactory::class)->create(
            package: $package,
            weeks: $weeks,
            member: ['first_name' => 'Ada', 'last_name' => 'Vale', 'email' => $email, 'phone' => null],
        );

        $order->forceFill([
            'status'             => MemberServiceOrderStatus::Paid,
            'paid_at'            => now(),
            'nmi_transaction_id' => '900900',
        ])->save();

        return $order->refresh();
    }

    // --- activation --------------------------------------------------------

    public function test_activating_a_paid_order_runs_for_the_weeks_bought(): void
    {
        $order    = $this->paidOrder(MemberServicePackage::Gold, 4);
        $property = Property::factory()->create();
        $staff    = User::factory()->create();

        $periods = app(AdvertisingActivator::class)
            ->activate($order, collect([$property]), $staff);

        $period = $periods->first();

        $this->assertSame(AdvertisingPeriodStatus::Active, $period->status);
        $this->assertSame(28, (int) $period->starts_at->diffInDays($period->ends_at));
        $this->assertSame($staff->id, $period->activated_by_user_id);
        $this->assertNotNull($period->activated_at);
    }

    /** Advertising that was never paid for must not exist. */
    public function test_an_unpaid_order_cannot_be_activated(): void
    {
        $order = app(MemberServiceOrderFactory::class)->create(
            package: MemberServicePackage::Bronze, weeks: 2,
            member: ['first_name' => 'A', 'last_name' => 'B', 'email' => 'x@example.com', 'phone' => null],
        );

        $this->expectException(RuntimeException::class);

        app(AdvertisingActivator::class)
            ->activate($order, collect([Property::factory()->create()]), User::factory()->create());
    }

    public function test_it_refuses_more_properties_than_the_package_covers(): void
    {
        $order = $this->paidOrder(MemberServicePackage::Bronze, 2);   // 1 property

        $this->expectExceptionMessage('covers 1 property; 2 were selected');

        app(AdvertisingActivator::class)->activate(
            $order,
            Property::factory()->count(2)->create(),
            User::factory()->create(),
        );
    }

    // --- the clock ---------------------------------------------------------

    /**
     * Status is read through the clock. There is no scheduler here, so without
     * this a period that ran out last week still reports active everywhere.
     */
    public function test_a_lapsed_period_reads_as_expired_without_a_sweep(): void
    {
        $period = AdvertisingPeriod::create([
            'member_service_order_id' => $this->paidOrder(MemberServicePackage::Silver, 1)->id,
            'property_id' => Property::factory()->create()->id,
            'starts_at'   => now()->subDays(30),
            'ends_at'     => now()->subDay(),
            'status'      => AdvertisingPeriodStatus::Active,
        ]);

        $this->assertSame(AdvertisingPeriodStatus::Active, $period->status);
        $this->assertSame(AdvertisingPeriodStatus::Expired, $period->effectiveStatus());
        $this->assertFalse($period->isLive());
        $this->assertSame(0, $period->daysRemaining(), 'Days remaining went negative.');
    }

    public function test_days_remaining_counts_down(): void
    {
        $period = AdvertisingPeriod::create([
            'member_service_order_id' => $this->paidOrder(MemberServicePackage::Gold, 4)->id,
            'property_id' => Property::factory()->create()->id,
            'starts_at'   => now()->subDays(4),
            'ends_at'     => now()->addDays(10),
            'status'      => AdvertisingPeriodStatus::Active,
        ]);

        $this->assertTrue($period->isLive());
        $this->assertSame(10, $period->daysRemaining());
    }

    // --- pause and resume ---------------------------------------------------

    /**
     * A member bought weeks of ADVERTISING, not weeks of calendar. Time lost
     * to a staff pause has to come back or they quietly get less than they
     * paid for.
     */
    public function test_resuming_returns_the_days_lost_to_a_pause(): void
    {
        $staff  = User::factory()->create();
        $period = AdvertisingPeriod::create([
            'member_service_order_id' => $this->paidOrder(MemberServicePackage::Gold, 4)->id,
            'property_id' => Property::factory()->create()->id,
            'starts_at'   => now()->subDays(2),
            'ends_at'     => now()->addDays(26),
            'status'      => AdvertisingPeriodStatus::Active,
        ]);

        $originalEnd = $period->ends_at->copy();

        $activator = app(AdvertisingActivator::class);
        $activator->pause($period, $staff);

        $this->travel(5)->days();

        $resumed = $activator->resume($period->refresh(), $staff);

        $this->assertSame(AdvertisingPeriodStatus::Active, $resumed->status);
        $this->assertSame(5, (int) $originalEnd->diffInDays($resumed->ends_at));
    }

    /** Extending a lapsed period gives the full extra time, not backdated. */
    public function test_extending_an_expired_period_starts_from_today(): void
    {
        $staff  = User::factory()->create();
        $period = AdvertisingPeriod::create([
            'member_service_order_id' => $this->paidOrder(MemberServicePackage::Silver, 1)->id,
            'property_id' => Property::factory()->create()->id,
            'starts_at'   => now()->subDays(30),
            'ends_at'     => now()->subDays(10),
            'status'      => AdvertisingPeriodStatus::Active,
        ]);

        $extended = app(AdvertisingActivator::class)->extend($period, 2, $staff);

        $this->assertTrue($extended->isLive());
        // Two full weeks from today, not from the date it lapsed.
        $this->assertSame(14, $extended->daysRemaining());
    }

    // --- evidence -----------------------------------------------------------

    /**
     * The gap this slice closes.
     *
     * The certificate read the retired bookings and charges tables, so a
     * member who paid for Member Services got an evidence pack showing their
     * logins and contracts but NOT the transaction — the one field a
     * processor actually asks for.
     */
    public function test_the_evidence_bundle_now_contains_the_payment_and_the_fulfilment(): void
    {
        $user     = User::factory()->create(['email' => 'ada@example.com']);
        $order    = $this->paidOrder(MemberServicePackage::Gold, 4, 'ada@example.com');
        $property = Property::factory()->create(['title' => 'Harbour View']);

        app(AdvertisingActivator::class)->activate($order, collect([$property]), User::factory()->create());

        $bundle = app(EvidenceBundleService::class)->generateForUser(
            $user->id,
            CarbonImmutable::now()->subDays(60),
            CarbonImmutable::now(),
        )->toArray();

        $this->assertCount(1, $bundle['member_service_orders']);
        $this->assertSame('900900', $bundle['member_service_orders'][0]['nmi_transaction_id']);
        $this->assertSame(179600, $bundle['member_service_orders'][0]['total_cents']);

        $this->assertCount(1, $bundle['advertising_periods']);
        $this->assertSame('Harbour View', $bundle['advertising_periods'][0]['property_title']);
    }

    /**
     * The template renders with the new sections.
     *
     * Goes through the real service rather than hand-assembling the view's
     * payload: the certificate needs more than the bundle (user name, email,
     * join date), and duplicating that here would test my copy of the payload
     * rather than the one production builds.
     */
    public function test_the_certificate_renders_with_the_new_sections(): void
    {
        $user  = User::factory()->create(['email' => 'ada@example.com']);
        $order = $this->paidOrder(MemberServicePackage::Gold, 4, 'ada@example.com');

        app(AdvertisingActivator::class)->activate(
            $order, collect([Property::factory()->create()]), User::factory()->create(),
        );

        $pdf = app(\App\Services\Chargeback\ChargebackCertificateService::class)->forUser(
            $user,
            CarbonImmutable::now()->subDays(60),
            CarbonImmutable::now(),
        );

        // A PDF, produced without the template erroring on the new sections.
        $this->assertNotEmpty($pdf);
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    /** An admin can download it for a member. */
    public function test_an_admin_can_download_the_certificate(): void
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $staff = User::factory()->create(['must_change_password' => false]);
        $staff->roles()->sync([\App\Models\Role::where('key', 'super_admin')->firstOrFail()->id]);

        $member = User::factory()->create(['email' => 'ada@example.com']);
        $this->paidOrder(MemberServicePackage::Gold, 4, 'ada@example.com');

        $this->actingAs($staff)
            ->get(route('admin.users.certificate', $member))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
