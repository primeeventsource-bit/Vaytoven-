<?php

namespace Tests\Feature\Admin;

use App\Enums\MemberServiceOrderStatus;
use App\Enums\MemberServicePackage;
use App\Enums\PropertyStatus;
use App\Enums\UserRole;
use App\Models\AdminAuditLog;
use App\Models\AdvertisingPeriod;
use App\Models\MemberOffer;
use App\Models\MemberServiceOrder;
use App\Models\Property;
use App\Models\PropertySnapshot;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Deleting listings and accounts.
 *
 * Both are irreversible, and both sit next to a reversible option that is
 * usually the right one — archive for a listing, deactivate for an account.
 * What these protect is the narrow set of cases where deletion would quietly
 * destroy the answer to a future dispute: the advertising periods that prove a
 * paid listing ran, and the order, contract and acceptance records behind a
 * member's charge. Those refuse and say what is holding them.
 */
class DeletionTest extends TestCase
{
    use RefreshDatabase;

    private function withRole(UserRole $role, string $key): User
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create(['role' => $role, 'must_change_password' => false]);
        $user->roles()->sync([Role::where('key', $key)->firstOrFail()->id]);

        return $user;
    }

    private function admin(): User
    {
        return $this->withRole(UserRole::SuperAdmin, 'super_admin');
    }

    private function listing(array $attributes = []): Property
    {
        return Property::factory()->create(array_merge([
            'host_id' => User::factory()->create()->id,
            'status'  => PropertyStatus::Draft->value,
        ], $attributes));
    }

    // --- listings ------------------------------------------------------------------

    public function test_an_admin_can_delete_a_listing(): void
    {
        $listing = $this->listing();

        $this->actingAs($this->admin())
            ->delete(route('admin.properties.destroy', $listing))
            ->assertRedirect(route('admin.properties.index'));

        $this->assertSame(0, Property::where('id', $listing->id)->count());
    }

    /** The record of what was removed has to outlive the thing it describes. */
    public function test_deleting_a_listing_is_audited_with_its_reference(): void
    {
        $listing = $this->listing();
        $reference = $listing->reference;

        $this->actingAs($this->admin())->delete(route('admin.properties.destroy', $listing));

        $log = AdminAuditLog::where('action', 'property.deleted')->sole();

        $this->assertSame($reference, $log->payload['reference']);
    }

    /**
     * The advertising periods and published snapshots are how "the service was
     * delivered" is proved when a member disputes the charge. They hang off
     * this row, so deleting it destroys the proof of the thing the money paid
     * for — and nobody notices until the dispute arrives.
     */
    public function test_a_listing_advertised_under_a_paid_order_cannot_be_deleted(): void
    {
        $listing = $this->listing(['status' => PropertyStatus::Active->value]);

        $order = MemberServiceOrder::create([
            'reference' => 'VYT-DEL1', 'email' => 'paid@example.com',
            'first_name' => 'Pat', 'last_name' => 'Doe',
            'package' => MemberServicePackage::Gold->value, 'weeks' => 4,
            'price_per_week_cents' => 44900, 'total_cents' => 179600, 'currency' => 'USD',
            'status' => MemberServiceOrderStatus::Paid->value, 'paid_at' => now(),
        ]);

        AdvertisingPeriod::create([
            'member_service_order_id' => $order->id,
            'property_id' => $listing->id,
            'starts_at' => now(), 'ends_at' => now()->addDays(30),
        ]);

        $this->actingAs($this->admin())
            ->delete(route('admin.properties.destroy', $listing))
            ->assertSessionHasErrors('delete');

        $this->assertSame(1, Property::where('id', $listing->id)->count());
    }

    public function test_a_listing_with_a_published_snapshot_cannot_be_deleted(): void
    {
        $listing = $this->listing();

        PropertySnapshot::create([
            'property_id'  => $listing->id,
            'reason'       => 'activated',
            'content'      => ['title' => $listing->title],
            'content_hash' => hash('sha256', 'x'),
            'captured_at'  => now(),
        ]);

        $this->actingAs($this->admin())
            ->delete(route('admin.properties.destroy', $listing))
            ->assertSessionHasErrors('delete');

        $this->assertSame(1, Property::where('id', $listing->id)->count());
    }

    /**
     * An offer is correspondence between two people. It outlives the
     * advertisement it was made against rather than vanishing with it.
     */
    public function test_offers_survive_the_listing_they_were_made_on(): void
    {
        $listing = $this->listing(['status' => PropertyStatus::Active->value, 'allow_offers' => true]);

        // No factory for offers; the columns that matter here are the
        // property link and a reference to find it by afterwards.
        $offer = MemberOffer::create([
            'reference'      => 'VAY-O-DEL',
            'direction'      => 'from_buyer',
            'kind'           => 'offer',
            'property_id'    => $listing->id,
            'buyer_user_id'  => User::factory()->create()->id,
            'member_user_id' => $listing->host_id,
            'status'         => 'pending',
            'sent_at'        => now(),
        ]);

        $this->actingAs($this->admin())->delete(route('admin.properties.destroy', $listing));

        $this->assertSame(1, MemberOffer::where('id', $offer->id)->count());
        $this->assertNull($offer->refresh()->property_id);
    }

    public function test_a_role_without_the_permission_cannot_delete_a_listing(): void
    {
        $listing = $this->listing();

        $this->actingAs($this->withRole(UserRole::Host, 'host'))
            ->delete(route('admin.properties.destroy', $listing))
            ->assertForbidden();

        $this->assertSame(1, Property::where('id', $listing->id)->count());
    }

    // --- accounts ------------------------------------------------------------------

    private function plainMember(): User
    {
        return User::factory()->create([
            'email'                => 'plain.member@example.com',
            'must_change_password' => false,
        ]);
    }

    public function test_an_admin_can_delete_an_account_with_nothing_attached(): void
    {
        $member = $this->plainMember();

        $this->actingAs($this->admin())
            ->delete(route('admin.users.destroy', $member))
            ->assertRedirect(route('admin.users.index'));

        $this->assertSame(0, User::where('id', $member->id)->count());
    }

    /**
     * A paid member's order, the terms version they accepted and the contract
     * they signed are what answer a chargeback. Deleting the account removes
     * the thread those hang from.
     */
    public function test_an_account_with_a_member_services_order_cannot_be_deleted(): void
    {
        $member = $this->plainMember();

        MemberServiceOrder::create([
            'reference' => 'VYT-DEL2', 'email' => $member->email,
            'first_name' => 'Plain', 'last_name' => 'Member',
            'package' => MemberServicePackage::Bronze->value, 'weeks' => 1,
            'price_per_week_cents' => 19900, 'total_cents' => 19900, 'currency' => 'USD',
            'status' => MemberServiceOrderStatus::Paid->value, 'paid_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->delete(route('admin.users.destroy', $member))
            ->assertSessionHasErrors('delete');

        $this->assertSame(1, User::where('id', $member->id)->count());
    }

    /** A member still holding listings is not a loose end to sweep away. */
    public function test_an_account_that_still_owns_listings_cannot_be_deleted(): void
    {
        $member = $this->plainMember();
        Property::factory()->create(['host_id' => $member->id]);

        $this->actingAs($this->admin())
            ->delete(route('admin.users.destroy', $member))
            ->assertSessionHasErrors('delete');

        $this->assertSame(1, User::where('id', $member->id)->count());
    }

    /** Irreversible plus locked out is a bad combination. */
    public function test_an_admin_cannot_delete_their_own_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $admin))
            ->assertStatus(422);

        $this->assertSame(1, User::where('id', $admin->id)->count());
    }

    public function test_a_lesser_admin_cannot_delete_a_super_admin(): void
    {
        $target = $this->withRole(UserRole::SuperAdmin, 'super_admin');
        $actor  = $this->withRole(UserRole::Admin, 'admin');

        $this->actingAs($actor)
            ->delete(route('admin.users.destroy', $target))
            ->assertForbidden();

        $this->assertSame(1, User::where('id', $target->id)->count());
    }

    public function test_a_role_without_the_permission_cannot_delete_an_account(): void
    {
        $member = $this->plainMember();

        $this->actingAs($this->withRole(UserRole::MemberSpecialist, 'member_specialist'))
            ->delete(route('admin.users.destroy', $member))
            ->assertForbidden();

        $this->assertSame(1, User::where('id', $member->id)->count());
    }

    public function test_deleting_an_account_is_audited_with_the_email(): void
    {
        $member = $this->plainMember();

        $this->actingAs($this->admin())->delete(route('admin.users.destroy', $member));

        $log = AdminAuditLog::where('action', 'user.deleted')->sole();

        $this->assertSame('plain.member@example.com', $log->payload['email']);
    }
}
