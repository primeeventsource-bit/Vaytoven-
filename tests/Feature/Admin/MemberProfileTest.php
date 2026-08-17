<?php

namespace Tests\Feature\Admin;

use App\Enums\MemberOfferStatus;
use App\Enums\MemberServiceOrderStatus;
use App\Enums\MemberServicePackage;
use App\Enums\OfferDirection;
use App\Enums\OfferKind;
use App\Models\MemberOffer;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Services\MemberServices\MemberServiceOrderFactory;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Member 360 screen.
 *
 * One page holding everything about one member, so staff stop opening ten
 * screens to answer one question. Tabs are server-rendered and switched with a
 * query parameter, which keeps every tab linkable and back-button-correct.
 */
class MemberProfileTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $roleKey = 'super_admin'): User
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create(['must_change_password' => false]);
        $user->roles()->sync([Role::where('key', $roleKey)->firstOrFail()->id]);

        return $user;
    }

    private function member(): User
    {
        return User::factory()->create([
            'name'  => 'Harriet Vance',
            'email' => 'harriet@example.com',
            'phone' => '+1 555 555 0180',
        ]);
    }

    private function paidOrder(User $member, MemberServicePackage $package, int $weeks): void
    {
        $order = app(MemberServiceOrderFactory::class)->create(
            package: $package,
            weeks: $weeks,
            member: [
                'first_name' => 'Harriet', 'last_name' => 'Vance',
                'email' => $member->email, 'phone' => $member->phone,
            ],
        );

        $order->forceFill([
            'status'             => MemberServiceOrderStatus::Paid,
            'paid_at'            => now(),
            'nmi_transaction_id' => '556677',
        ])->save();
    }

    // --- access -----------------------------------------------------------

    public function test_an_admin_can_open_a_member_profile(): void
    {
        $member = $this->member();

        $this->actingAs($this->staff())
            ->get(route('admin.members.show', $member))
            ->assertOk()
            ->assertSee('Harriet Vance')
            ->assertSee('harriet@example.com');
    }

    public function test_a_role_without_member_access_is_refused(): void
    {
        $this->actingAs($this->staff('host'))
            ->get(route('admin.members.show', $this->member()))
            ->assertForbidden();
    }

    public function test_a_visitor_is_sent_to_login(): void
    {
        $this->get(route('admin.members.show', $this->member()))
            ->assertRedirect(route('login'));
    }

    // --- tabs -------------------------------------------------------------

    public function test_every_tab_renders(): void
    {
        $member = $this->member();
        $this->paidOrder($member, MemberServicePackage::Gold, 4);
        Property::factory()->create(['host_id' => $member->id, 'title' => 'Bayfront Retreat']);

        $staff = $this->staff();

        foreach (['overview', 'properties', 'analytics', 'offers', 'documents', 'payments', 'activity'] as $tab) {
            $this->actingAs($staff)
                ->get(route('admin.members.show', ['user' => $member, 'tab' => $tab]))
                ->assertOk();
        }
    }

    /** An unknown tab falls back rather than 404ing on a typo in a shared link. */
    public function test_an_unknown_tab_falls_back_to_overview(): void
    {
        $this->actingAs($this->staff())
            ->get(route('admin.members.show', ['user' => $this->member(), 'tab' => 'nonsense']))
            ->assertOk()
            ->assertSee('Staff notes');
    }

    // --- the data -----------------------------------------------------------

    public function test_the_package_and_payment_are_shown(): void
    {
        $member = $this->member();
        $this->paidOrder($member, MemberServicePackage::Gold, 4);

        $this->actingAs($this->staff())
            ->get(route('admin.members.show', ['user' => $member, 'tab' => 'payments']))
            ->assertOk()
            ->assertSee('1,796.00')       // Gold x 4
            ->assertSee('556677');        // NMI transaction
    }

    /**
     * Offers in both directions. Showing only one side is how staff conclude a
     * member has no activity when they have plenty.
     */
    public function test_offers_are_shown_in_both_directions(): void
    {
        $member = $this->member();
        $other  = User::factory()->create();

        $received = Property::factory()->create(['host_id' => $member->id, 'title' => 'Their Listing']);
        $sentOn   = Property::factory()->create(['host_id' => $other->id, 'title' => 'Someone Elses']);

        foreach ([
            ['member_user_id' => $member->id, 'buyer_user_id' => $other->id, 'property_id' => $received->id],
            ['member_user_id' => $other->id, 'buyer_user_id' => $member->id, 'property_id' => $sentOn->id],
        ] as $row) {
            MemberOffer::create($row + [
                'direction' => OfferDirection::FromBuyer,
                'kind' => OfferKind::Offer,
                'proposed_check_in' => now()->addDays(10),
                'proposed_check_out' => now()->addDays(12),
                'offer_amount_cents' => 150000,
                'status' => MemberOfferStatus::Active,
                'sent_at' => now(),
                'expires_at' => now()->addDay(),
            ]);
        }

        $this->actingAs($this->staff())
            ->get(route('admin.members.show', ['user' => $member, 'tab' => 'offers']))
            ->assertOk()
            ->assertSee('Their Listing')
            ->assertSee('Someone Elses')
            ->assertSee('Received')
            ->assertSee('Sent');
    }

    /** Over-allowance is surfaced, not silently enforced. */
    public function test_it_flags_more_properties_than_the_package_allows(): void
    {
        $member = $this->member();
        $this->paidOrder($member, MemberServicePackage::Bronze, 2);   // 1 property

        Property::factory()->count(2)->create(['host_id' => $member->id]);

        $this->actingAs($this->staff())
            ->get(route('admin.members.show', ['user' => $member, 'tab' => 'properties']))
            ->assertOk()
            ->assertSee('Over the package allowance');
    }

    public function test_the_activity_log_records_account_and_payment_events(): void
    {
        $member = $this->member();
        $this->paidOrder($member, MemberServicePackage::Silver, 3);

        $this->actingAs($this->staff())
            ->get(route('admin.members.show', ['user' => $member, 'tab' => 'activity']))
            ->assertOk()
            ->assertSee('Account created')
            ->assertSee('Payment approved')
            ->assertSee('Silver package selected');
    }

    // --- notes ------------------------------------------------------------

    public function test_staff_notes_can_be_saved_and_are_audited(): void
    {
        $member = $this->member();
        $staff  = $this->staff();

        $this->actingAs($staff)
            ->post(route('admin.members.notes', $member), [
                'staff_notes' => 'Prefers to be called about renewals, not emailed.',
            ])
            ->assertRedirect();

        $this->assertSame(
            'Prefers to be called about renewals, not emailed.',
            $member->refresh()->staff_notes,
        );

        $this->assertDatabaseHas('admin_audit_logs', [
            'action'     => 'user.notes_updated',
            'subject_id' => $member->id,
        ]);
    }

    /** The note body must not be copied into the audit payload. */
    public function test_the_note_body_is_not_duplicated_into_the_audit_log(): void
    {
        $member = $this->member();

        $this->actingAs($this->staff())->post(route('admin.members.notes', $member), [
            'staff_notes' => 'Something private about this member.',
        ]);

        $log = \App\Models\AdminAuditLog::where('action', 'user.notes_updated')->first();

        $this->assertStringNotContainsString('Something private', json_encode($log->payload));
    }
}
