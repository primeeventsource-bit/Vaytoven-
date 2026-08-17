<?php

namespace Tests\Feature\Admin;

use App\Enums\MemberOfferStatus;
use App\Enums\OfferKind;
use App\Enums\UserRole;
use App\Models\MemberOffer;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Services\Offers\OfferService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Offer Center: every inquiry, quotable, with what happened to it.
 *
 * Two facts drive the design. An offer needs a reference staff can read down
 * the phone, and it needs to record whether the listing member ever OPENED it
 * — because "they never replied" has two very different causes and they need
 * different conversations.
 */
class OfferCenterTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create(['must_change_password' => false]);
        $user->roles()->sync([Role::where('key', 'super_admin')->firstOrFail()->id]);

        return $user;
    }

    private function submittedOffer(): MemberOffer
    {
        $owner = User::factory()->create(['role' => UserRole::Host]);
        $buyer = User::factory()->create(['role' => UserRole::Traveler, 'name' => 'Rosa Iqbal']);
        $property = Property::factory()->create(['host_id' => $owner->id, 'title' => 'Dune House']);

        return app(OfferService::class)->submit(
            property: $property,
            buyer: $buyer,
            kind: OfferKind::Offer,
            amountCents: 150000,
            message: 'Are these dates free?',
            ipAddress: '203.0.113.9',
            checkIn: now()->addDays(20)->toDateString(),
            checkOut: now()->addDays(27)->toDateString(),
            guests: 4,
        );
    }

    // --- references ---------------------------------------------------------

    public function test_every_submitted_offer_gets_a_quotable_reference(): void
    {
        $offer = $this->submittedOffer();

        $this->assertMatchesRegularExpression('/^VT-[A-Z2-9]{6}$/', $offer->reference);
    }

    /** Random, not sequential — a counter lets anyone walk the range. */
    public function test_references_are_unique_and_not_sequential(): void
    {
        $refs = collect(range(1, 4))->map(fn () => $this->submittedOffer()->reference);

        $this->assertCount(4, $refs->unique());

        $bodies = $refs->map(fn ($r) => substr($r, 3));
        $this->assertCount(4, $bodies->map(fn ($b) => substr($b, 0, 4))->unique(),
            'References share a prefix — they look counter-derived.');
    }

    // --- viewed tracking ----------------------------------------------------

    public function test_an_offer_starts_unopened(): void
    {
        $this->assertNull($this->submittedOffer()->viewed_at);
    }

    public function test_it_records_when_the_listing_owner_opens_their_offers(): void
    {
        $offer = $this->submittedOffer();
        $owner = User::find($offer->member_user_id);

        $this->actingAs($owner)->get(route('offers.index'))->assertOk();

        $this->assertNotNull($offer->refresh()->viewed_at);
    }

    /**
     * Only the FIRST view is kept. Overwriting on every load would destroy the
     * fact worth having — whether it lapsed unopened or was read and ignored.
     */
    public function test_the_viewed_timestamp_is_not_overwritten(): void
    {
        $offer = $this->submittedOffer();
        $owner = User::find($offer->member_user_id);

        $this->actingAs($owner)->get(route('offers.index'));
        $first = $offer->refresh()->viewed_at;

        $this->travel(2)->hours();

        $this->actingAs($owner)->get(route('offers.index'));

        $this->assertEquals($first, $offer->refresh()->viewed_at);
    }

    /** A buyer looking at their own dashboard must not mark it opened. */
    public function test_the_buyer_viewing_their_own_offer_does_not_mark_it_opened(): void
    {
        $offer = $this->submittedOffer();
        $buyer = User::find($offer->buyer_user_id);

        $this->actingAs($buyer)->get('/dashboard')->assertOk();

        $this->assertNull($offer->refresh()->viewed_at);
    }

    // --- the register and the record ---------------------------------------

    public function test_the_register_lists_offers_with_their_reference(): void
    {
        $offer = $this->submittedOffer();

        $this->actingAs($this->staff())
            ->get(route('admin.offers.index'))
            ->assertOk()
            ->assertSee($offer->reference)
            ->assertSee('Rosa Iqbal');
    }

    public function test_the_detail_page_shows_the_whole_request(): void
    {
        $offer = $this->submittedOffer();

        $this->actingAs($this->staff())
            ->get(route('admin.offers.show', $offer))
            ->assertOk()
            ->assertSee($offer->reference)
            ->assertSee('Dune House')
            ->assertSee('Rosa Iqbal')
            ->assertSee('1,500.00')
            ->assertSee('Are these dates free?')
            ->assertSee('203.0.113.9');
    }

    public function test_the_detail_page_shows_an_activity_timeline(): void
    {
        $offer = $this->submittedOffer();
        $owner = User::find($offer->member_user_id);

        $this->actingAs($owner)->get(route('offers.index'));

        $this->actingAs($this->staff())
            ->get(route('admin.offers.show', $offer))
            ->assertOk()
            ->assertSee('Submitted')
            ->assertSee('Opened by the listing member');
    }

    /**
     * The distinction the platform turns on: staff must not tell a caller that
     * an accepted offer is a reservation Vaytoven holds.
     */
    public function test_an_accepted_offer_says_it_is_not_a_reservation(): void
    {
        $offer = $this->submittedOffer();
        $offer->forceFill([
            'status'       => MemberOfferStatus::Accepted,
            'responded_at' => now(),
        ])->save();

        $this->actingAs($this->staff())
            ->get(route('admin.offers.show', $offer))
            ->assertOk()
            ->assertSee('Accepted is not a reservation');
    }

    public function test_an_expired_unopened_offer_is_called_out(): void
    {
        $offer = $this->submittedOffer();
        $offer->forceFill(['expires_at' => now()->subHour()])->save();

        $this->actingAs($this->staff())
            ->get(route('admin.offers.index'))
            ->assertOk()
            ->assertSee('never opened');
    }

    // --- access -------------------------------------------------------------

    public function test_a_visitor_cannot_open_an_offer_record(): void
    {
        $this->get(route('admin.offers.show', $this->submittedOffer()))
            ->assertRedirect(route('login'));
    }
}
