<?php

namespace Tests\Feature\Admin;

use App\Enums\MemberServicePackage;
use App\Enums\OfferKind;
use App\Enums\UserRole;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Services\MemberServices\MemberServiceOrderFactory;
use App\Services\Offers\OfferService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One search box over everything staff need to find.
 *
 * The realistic use is a phone ringing: somebody reads out an email, a phone
 * number, an offer reference or a transaction id, and whoever answers needs
 * the record before the caller finishes the sentence.
 */
class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $roleKey = 'super_admin'): User
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create(['must_change_password' => false]);
        $user->roles()->sync([Role::where('key', $roleKey)->firstOrFail()->id]);

        return $user;
    }

    private function search(User $staff, string $term)
    {
        return $this->actingAs($staff)->get(route('admin.search', ['q' => $term]));
    }

    // --- finding people ----------------------------------------------------

    public function test_it_finds_a_member_by_name_and_by_email(): void
    {
        $staff = $this->staff();
        User::factory()->create(['name' => 'Constance Ferrer', 'email' => 'constance@example.com']);

        $this->search($staff, 'Ferrer')->assertOk()->assertSee('constance@example.com');
        $this->search($staff, 'constance@example')->assertOk()->assertSee('Constance Ferrer');
    }

    /**
     * The one that matters most on a phone call.
     *
     * A caller reads "(877) 782-9868"; the row was saved as "+18777829868".
     * Comparing the digits alone is what makes those the same number.
     */
    public function test_it_finds_a_member_by_phone_in_a_different_format(): void
    {
        $staff = $this->staff();
        User::factory()->create(['name' => 'Dial Test', 'phone' => '+18777829868']);

        $this->search($staff, '(877) 782-9868')->assertOk()->assertSee('Dial Test');
        $this->search($staff, '877-782-9868')->assertOk()->assertSee('Dial Test');
    }

    public function test_it_finds_a_member_by_id(): void
    {
        $staff  = $this->staff();
        $member = User::factory()->create(['name' => 'Numbered Person']);

        $this->search($staff, (string) $member->id)->assertOk()->assertSee('Numbered Person');
    }

    // --- finding records ----------------------------------------------------

    public function test_it_finds_a_property_by_title_and_city(): void
    {
        $staff = $this->staff();
        Property::factory()->create(['title' => 'Lighthouse Keepers Cottage', 'city' => 'Wells']);

        $this->search($staff, 'Lighthouse')->assertOk()->assertSee('Lighthouse Keepers Cottage');
        $this->search($staff, 'Wells')->assertOk()->assertSee('Lighthouse Keepers Cottage');
    }

    public function test_it_finds_an_offer_by_its_reference(): void
    {
        $staff = $this->staff();

        $owner = User::factory()->create(['role' => UserRole::Host]);
        $buyer = User::factory()->create(['role' => UserRole::Traveler]);

        $offer = app(OfferService::class)->submit(
            property: Property::factory()->create(['host_id' => $owner->id, 'title' => 'Reef Cabin']),
            buyer: $buyer,
            kind: OfferKind::Offer,
            amountCents: 90000,
            message: null,
            ipAddress: '198.51.100.4',
        );

        $this->search($staff, $offer->reference)->assertOk()->assertSee('Reef Cabin');
    }

    /**
     * When a processor opens a dispute, the transaction id is all anyone has.
     * Hunting for it by hand is the slow part of responding.
     */
    public function test_it_finds_an_order_by_its_nmi_transaction_id(): void
    {
        $staff = $this->staff();

        $order = app(MemberServiceOrderFactory::class)->create(
            package: MemberServicePackage::Gold, weeks: 4,
            member: ['first_name' => 'Ines', 'last_name' => 'Aguirre', 'email' => 'ines@example.com', 'phone' => null],
        );
        $order->forceFill(['nmi_transaction_id' => '778899'])->save();

        $this->search($staff, '778899')->assertOk()->assertSee($order->reference);
    }

    public function test_it_finds_an_order_by_reference(): void
    {
        $staff = $this->staff();

        $order = app(MemberServiceOrderFactory::class)->create(
            package: MemberServicePackage::Silver, weeks: 2,
            member: ['first_name' => 'Ines', 'last_name' => 'Aguirre', 'email' => 'ines@example.com', 'phone' => null],
        );

        $this->search($staff, $order->reference)->assertOk()->assertSee('ines@example.com');
    }

    // --- behaviour ----------------------------------------------------------

    public function test_a_single_character_does_not_search(): void
    {
        $staff = $this->staff();
        User::factory()->create(['name' => 'Aaron Blake']);

        $this->search($staff, 'a')->assertOk()->assertSee('Type at least two characters');
    }

    public function test_it_says_so_when_nothing_matches(): void
    {
        $this->search($this->staff(), 'zzzznotarealthing')
            ->assertOk()
            ->assertSee('Nothing matched');
    }

    // --- access -------------------------------------------------------------

    public function test_support_can_search(): void
    {
        $this->search($this->staff('support'), 'anything')->assertOk();
    }

    public function test_a_host_cannot(): void
    {
        $this->search($this->staff('host'), 'anything')->assertForbidden();
    }

    public function test_a_visitor_is_sent_to_login(): void
    {
        $this->get(route('admin.search', ['q' => 'test']))->assertRedirect(route('login'));
    }

    /** The box is only rendered for accounts that can use it. */
    public function test_the_header_box_is_hidden_from_accounts_without_access(): void
    {
        $host = $this->staff('host');

        $this->actingAs($host)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Search members, properties, offers');
    }

    // --- the new roles -------------------------------------------------------

    public function test_support_can_read_but_not_edit(): void
    {
        $support = $this->staff('support');

        $this->assertTrue($support->hasPermission('users.view'));
        $this->assertTrue($support->hasPermission('offers.view'));
        $this->assertFalse($support->hasPermission('users.edit'));
        $this->assertFalse($support->hasPermission('properties.publish'));
    }

    /**
     * Marketing must never reach billing. Campaign work needs traffic and
     * conversion, never a card transaction or a payment history.
     */
    public function test_marketing_has_analytics_but_no_billing_access(): void
    {
        $marketing = $this->staff('marketing');

        $this->assertTrue($marketing->hasPermission('reports.view'));
        $this->assertTrue($marketing->hasPermission('properties.view'));
        $this->assertFalse($marketing->hasPermission('billing.view'));
        $this->assertFalse($marketing->hasPermission('billing.manage'));
    }
}
