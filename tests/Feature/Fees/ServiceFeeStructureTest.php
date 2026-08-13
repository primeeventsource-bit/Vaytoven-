<?php

namespace Tests\Feature\Fees;

use App\Enums\FeeStructure;
use App\Enums\PropertyStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Property;
use App\Models\ServiceFeeConfig;
use App\Models\User;
use App\Services\Bookings\QuoteCalculator;
use App\Services\Fees\ResolvedServiceFee;
use App\Services\Fees\ServiceFeeResolver;
use Database\Seeders\RbacSeeder;
use Database\Seeders\ServiceFeeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The fee structure is now an admin-configured pricing model with no customer
 * checkout in front of it — the guest-facing assertions that used to live here
 * went with the booking product. What remains is worth keeping: resolution
 * order, basis-point arithmetic, immutable snapshots and the admin console.
 */
class ServiceFeeStructureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ServiceFeeSeeder::class);
        ServiceFeeResolver::bustCache();
    }

    private function property(array $attributes = []): Property
    {
        return Property::factory()->create($attributes + [
            'status' => PropertyStatus::Active->value,
            'base_nightly_cents' => 100000,   // $1,000 for a one-night stay
            'cleaning_fee_cents' => 0,
            // The factory's defaults would otherwise bounce a 1-night, 2-guest
            // quote off the minimum-stay and capacity guards before it reaches
            // the price breakdown.
            'minimum_nights' => 1,
            'capacity' => 6,
        ]);
    }

    /**
     * The booking funnel sits behind `terms.current`, so a traveler who has
     * not accepted the current legal documents is redirected out of it.
     */
    private function traveler(): User
    {
        app(\App\Services\Legal\LegalDocumentRegistry::class)->materialiseAll();

        $user = User::factory()->create([
            'role' => UserRole::Traveler,
            'email_verified_at' => now(),
        ]);

        foreach (app(\App\Services\Legal\LegalDocumentRegistry::class)->registrationRequired() as $version) {
            \App\Models\TermsAcceptance::create([
                'user_id' => $user->id,
                'terms_version_id' => $version->id,
                'accepted_at' => now(),
            ]);
        }

        return $user;
    }

    private function setDefault(FeeStructure $structure, int $guestBps = 1500): void
    {
        ServiceFeeConfig::query()->where('scope_type', 'default')->update([
            'fee_structure' => $structure->value,
            'split_guest_bps' => $guestBps,
        ]);
        ServiceFeeResolver::bustCache();
    }

    // --- The two worked examples from the specification -------------------

    public function test_split_fee_matches_the_worked_example(): void
    {
        // $1,000 stay · host 3% · guest 15%
        $this->setDefault(FeeStructure::Split, 1500);

        $q = QuoteCalculator::breakdown(100000, 1, 0, $this->property());

        $this->assertSame('split', $q['fee_structure']);
        $this->assertSame(100000, $q['subtotal_cents']);          // base $1,000
        $this->assertSame(3000, $q['host_fee_cents']);            // host fee $30
        $this->assertSame(15000, $q['service_fee_cents']);        // guest fee $150
        $this->assertSame(97000, $q['host_net_cents']);           // host net $970
        // Guest total before tax = $1,150.
        $this->assertSame(115000, $q['subtotal_cents'] + $q['service_fee_cents']);
    }

    public function test_single_fee_matches_the_worked_example(): void
    {
        // $1,000 stay · host 15.5% · guest 0%
        $this->setDefault(FeeStructure::Single);

        $q = QuoteCalculator::breakdown(100000, 1, 0, $this->property());

        $this->assertSame('single', $q['fee_structure']);
        $this->assertSame(15500, $q['host_fee_cents']);           // host fee $155
        $this->assertSame(0, $q['service_fee_cents']);            // guest fee $0
        $this->assertSame(84500, $q['host_net_cents']);           // host net $845
        // Guest total before tax equals the stay price exactly.
        $this->assertSame(100000, $q['subtotal_cents'] + $q['service_fee_cents']);
    }

    public function test_rates_are_stored_as_basis_points_so_fractions_survive(): void
    {
        $this->setDefault(FeeStructure::Single);

        $q = QuoteCalculator::breakdown(100000, 1, 0, $this->property());

        // 15.5% is not expressible as a whole percent — this is why the column
        // is basis points.
        $this->assertSame(1550, $q['host_fee_bps']);
        $this->assertSame('15.5%', ResolvedServiceFee::formatBps(1550));
        $this->assertSame('14.1%', ResolvedServiceFee::formatBps(1410));
        $this->assertSame('3%', ResolvedServiceFee::formatBps(300));
    }

    // --- Resolution hierarchy ---------------------------------------------

    public function test_a_property_override_beats_a_host_override(): void
    {
        $host = User::factory()->create(['role' => UserRole::Host]);
        $property = $this->property(['host_id' => $host->id]);

        ServiceFeeConfig::query()->create([
            'name' => 'Host deal', 'scope_type' => 'host', 'scope_value' => (string) $host->id,
            'fee_structure' => 'split', 'split_host_bps' => 500,
            'split_guest_bps' => 1500, 'single_host_bps' => 1550, 'active' => true,
        ]);
        ServiceFeeConfig::query()->create([
            'name' => 'Property deal', 'scope_type' => 'property', 'scope_value' => (string) $property->id,
            'fee_structure' => 'split', 'split_host_bps' => 100,
            'split_guest_bps' => 1500, 'single_host_bps' => 1550, 'active' => true,
        ]);
        ServiceFeeResolver::bustCache();

        $q = QuoteCalculator::breakdown(100000, 1, 0, $property->fresh());

        $this->assertSame(100, $q['host_fee_bps'], 'The property-scoped config should win.');
    }

    public function test_a_structure_set_on_the_property_overrides_the_config_structure(): void
    {
        $this->setDefault(FeeStructure::Split);
        $property = $this->property(['fee_structure' => 'single']);

        $q = QuoteCalculator::breakdown(100000, 1, 0, $property);

        $this->assertSame('single', $q['fee_structure']);
        $this->assertSame(0, $q['service_fee_cents']);
    }

    public function test_an_inactive_or_expired_config_is_ignored(): void
    {
        $property = $this->property();

        ServiceFeeConfig::query()->create([
            'name' => 'Expired promo', 'scope_type' => 'property', 'scope_value' => (string) $property->id,
            'fee_structure' => 'split', 'split_host_bps' => 1, 'split_guest_bps' => 1410,
            'single_host_bps' => 1550, 'active' => true,
            'effective_to' => now()->subDay(),
        ]);
        ServiceFeeResolver::bustCache();

        $q = QuoteCalculator::breakdown(100000, 1, 0, $property);

        $this->assertSame(300, $q['host_fee_bps'], 'An expired config must fall through to the default.');
    }

    public function test_with_no_configuration_the_pre_existing_fee_settings_still_apply(): void
    {
        ServiceFeeConfig::query()->delete();
        ServiceFeeResolver::bustCache();

        // The legacy Settings-console values must keep driving the quote until
        // a configuration exists, so an operator editing them never silently
        // sees no effect.
        $q = QuoteCalculator::breakdown(100000, 1, 0, $this->property());

        $this->assertSame(
            QuoteCalculator::guestServicePct() * 100,
            $q['guest_fee_bps'],
        );
        $this->assertSame(((int) setting('fees.host_commission_pct', 3)) * 100, $q['host_fee_bps']);
    }

    // --- The breakdown carries a full snapshot -----------------------------

    /**
     * Three tests here used to create a booking through BookingService and
     * assert that its stored rates survived a later pricing change. Nothing
     * creates a booking any more, so there is no stored snapshot to protect.
     *
     * What survives is the calculator that produced it, which the admin fee
     * console still uses to preview a structure. It must emit every snapshot
     * field — a caller that persists a quote needs the rates, not just the
     * cents, or the figure becomes unexplainable the moment pricing changes.
     */
    public function test_the_breakdown_emits_every_snapshot_field(): void
    {
        $this->setDefault(FeeStructure::Split, 1410);
        $property = $this->property();

        $quote = app(QuoteCalculator::class)->breakdown(
            rateCents: 100000, nights: 1, cleaningCents: 0, property: $property,
        );

        foreach ([
            'fee_structure', 'host_fee_bps', 'host_fee_cents',
            'guest_fee_bps', 'host_net_cents', 'service_fee_config_id',
        ] as $field) {
            $this->assertArrayHasKey($field, $quote, "Breakdown is missing {$field}");
            $this->assertNotNull($quote[$field], "Breakdown has a null {$field}");
        }

        $this->assertSame(1410, $quote['guest_fee_bps']);
        $this->assertSame(300, $quote['host_fee_bps']);
        $this->assertSame(3000, $quote['host_fee_cents']);
        $this->assertSame(97000, $quote['host_net_cents']);
    }

    /** A pricing change is reflected in the next quote, not retro-applied. */
    public function test_a_pricing_change_applies_to_the_next_quote(): void
    {
        $this->setDefault(FeeStructure::Split, 1500);
        $property = $this->property();

        // The breakdown carries the structure as its backing string value, not
        // the enum — it is built to be persisted as a snapshot.
        $before = app(QuoteCalculator::class)->breakdown(100000, 1, 0, $property);
        $this->assertSame(FeeStructure::Split->value, $before['fee_structure']);
        $this->assertSame(300, $before['host_fee_bps']);

        ServiceFeeConfig::query()->where('scope_type', 'default')->update([
            'fee_structure' => 'single', 'single_host_bps' => 1550,
        ]);
        ServiceFeeResolver::bustCache();

        $after = app(QuoteCalculator::class)->breakdown(100000, 1, 0, $property);

        $this->assertSame(FeeStructure::Single->value, $after['fee_structure']);
        $this->assertSame(1550, $after['host_fee_bps']);
        $this->assertSame(0, $after['guest_fee_bps'], 'Single-Fee charges the guest nothing.');
    }

    // --- Guest-facing surfaces --------------------------------------------

    /**
     * No customer surface quotes a guest service fee any more.
     *
     * Two tests here used to assert what the checkout showed a guest under
     * each fee structure. There is no checkout, and more to the point there is
     * no guest-facing fee at all: Vaytoven bills the host for advertising and
     * takes nothing from what a traveler pays.
     */
    public function test_no_public_page_quotes_a_guest_service_fee(): void
    {
        $this->setDefault(FeeStructure::Split, 1500);
        $property = $this->property();

        foreach (['/', '/properties', route('properties.show', $property)] as $url) {
            $this->get($url)->assertOk()->assertDontSee('Guest Service Fee');
        }
    }

    /** The system must never state or imply an Airbnb affiliation. */
    public function test_no_customer_surface_mentions_airbnb(): void
    {
        $property = $this->property();

        $pages = [
            $this->get('/')->getContent(),
            $this->get('/properties')->getContent(),
            $this->get(route('properties.show', $property))->getContent(),
            $this->get('/earnings-calculator')->getContent(),
        ];

        foreach ($pages as $i => $html) {
            $this->assertStringNotContainsStringIgnoringCase('airbnb', $html, "Page #{$i} references Airbnb.");
        }
    }

    // --- Admin console -----------------------------------------------------

    public function test_an_admin_can_change_the_rates_without_a_deploy(): void
    {
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $config = ServiceFeeConfig::query()->where('scope_type', 'default')->firstOrFail();

        $this->actingAs($admin)->put(route('admin.hosting.service-fees.update', $config), [
            'name' => 'Platform default', 'scope_type' => 'default',
            'fee_structure' => 'split',
            'split_host_pct' => '3', 'split_guest_pct' => '16.5', 'single_host_pct' => '15.5',
            'active' => '1',
        ])->assertRedirect();

        $this->assertSame(1650, $config->fresh()->split_guest_bps);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'service_fee.update']);
    }

    public function test_a_guest_rate_outside_the_required_band_is_rejected(): void
    {
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $config = ServiceFeeConfig::query()->where('scope_type', 'default')->firstOrFail();

        $this->actingAs($admin)->put(route('admin.hosting.service-fees.update', $config), [
            'name' => 'Too low', 'scope_type' => 'default', 'fee_structure' => 'split',
            'split_host_pct' => '3', 'split_guest_pct' => '10', 'single_host_pct' => '15.5',
            'active' => '1',
        ])->assertStatus(422);

        $this->assertSame(1500, $config->fresh()->split_guest_bps, 'The rate must be unchanged.');
    }

    public function test_the_platform_default_cannot_be_deleted(): void
    {
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $config = ServiceFeeConfig::query()->where('scope_type', 'default')->firstOrFail();

        $this->actingAs($admin)
            ->delete(route('admin.hosting.service-fees.destroy', $config))
            ->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseHas('service_fee_configs', ['id' => $config->id]);
    }

    public function test_a_role_without_the_permission_cannot_reach_the_screen(): void
    {
        $this->seed(RbacSeeder::class);
        $traveler = User::factory()->create(['role' => UserRole::Traveler]);

        $this->actingAs($traveler)->get(route('admin.hosting.service-fees'))->assertForbidden();
    }

    // --- Host dashboard ----------------------------------------------------

    /**
     * The inverse of the test that was here.
     *
     * The host dashboard used to render a per-booking fee breakdown — booking
     * amount, host service fee, host net. It showed money Vaytoven was said to
     * be deducting from a host's earnings, which is not what happens: the host
     * pays to advertise and keeps what the guest pays. The panel is gone, and
     * this makes sure it does not come back.
     */
    public function test_the_host_dashboard_shows_no_per_booking_fee_deduction(): void
    {
        $this->setDefault(FeeStructure::Single);
        $host = User::factory()->create(['role' => UserRole::Host]);
        $this->property(['host_id' => $host->id]);

        $this->actingAs($host)->get('/dashboard')->assertOk()
            ->assertDontSee('Host Service Fee')
            ->assertDontSee('Recent bookings')
            ->assertDontSee('Host net');
    }
}
