<?php

namespace Tests\Feature\Offers;

use App\Enums\MemberOfferStatus;
use App\Enums\PropertyStatus;
use App\Enums\UserRole;
use App\Models\MemberOffer;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\EnablesStayCheckout;
use Tests\TestCase;

/**
 * Vaytoven is a SaaS advertising and marketing platform. It does not act as a
 * booking platform, collect rental funds, or process payments between
 * travelers and property owners.
 *
 * These tests hold the product to that: a visitor submits an OFFER, nothing is
 * reserved, nothing is charged, and the stay checkout is unreachable.
 */
class SubmitOfferFlowTest extends TestCase
{
    use EnablesStayCheckout, RefreshDatabase;

    private function property(array $attributes = []): Property
    {
        return Property::factory()->create($attributes + [
            'status' => PropertyStatus::Active->value,
            'base_nightly_cents' => 40000,
            'capacity' => 6,
            'minimum_nights' => 1,
        ]);
    }

    // --- The listing page offers, it does not sell -------------------------

    public function test_the_listing_page_shows_submit_offer_not_a_booking_cta(): void
    {
        $property = $this->property();
        $buyer = User::factory()->create(['role' => UserRole::Traveler]);

        $html = $this->actingAs($buyer)
            ->get(route('properties.show', $property))->assertOk()->getContent();

        $this->assertStringContainsString('SUBMIT OFFER', $html);

        foreach (['Book Now', 'Reserve Now', 'Proceed to Checkout', 'Continue to review'] as $banned) {
            $this->assertStringNotContainsString($banned, $html, "Listing page still says “{$banned}”.");
        }
    }

    public function test_the_listing_page_states_that_no_reservation_or_charge_occurs(): void
    {
        $property = $this->property();
        $buyer = User::factory()->create(['role' => UserRole::Traveler]);

        $text = preg_replace('/\s+/', ' ', strip_tags(
            $this->actingAs($buyer)->get(route('properties.show', $property))->getContent()
        ));

        $this->assertStringContainsString(
            'does not create a confirmed reservation and does not charge you for the stay',
            $text,
        );
    }

    // --- Submission captures everything the member dashboard must show -----

    public function test_an_offer_captures_dates_guests_amount_and_provenance(): void
    {
        $property = $this->property();
        $buyer = User::factory()->create(['role' => UserRole::Traveler]);

        $checkIn = now()->addDays(30)->toDateString();
        $checkOut = now()->addDays(35)->toDateString();

        $this->actingAs($buyer)->post(route('offers.store', $property), [
            'kind' => 'offer',
            'amount_dollars' => '1800',
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'guests' => 4,
            'message' => 'Travelling with two children, flexible by a day either side.',
        ])->assertRedirect();

        $offer = MemberOffer::query()->sole();

        $this->assertSame($property->id, $offer->property_id);
        $this->assertSame($buyer->id, $offer->buyer_user_id);
        $this->assertSame(180000, $offer->offer_amount_cents);
        $this->assertSame($checkIn, $offer->proposed_check_in->toDateString());
        $this->assertSame($checkOut, $offer->proposed_check_out->toDateString());
        $this->assertSame(4, $offer->proposed_guests);
        $this->assertNotNull($offer->submitted_ip);
        $this->assertNotNull($offer->sent_at);
        $this->assertSame(MemberOfferStatus::Active, $offer->status);
        $this->assertSame(24, (int) $offer->sent_at->diffInHours($offer->expires_at));
    }

    public function test_the_confirmation_says_it_is_not_a_reservation(): void
    {
        $property = $this->property();
        $buyer = User::factory()->create(['role' => UserRole::Traveler]);

        $this->actingAs($buyer)
            ->post(route('offers.store', $property), ['kind' => 'inquiry', 'guests' => 2])
            ->assertSessionHas('success', function (string $message) {
                return str_contains($message, 'submitted to the listing member for review')
                    && str_contains($message, 'not a confirmed reservation');
            });
    }

    public function test_check_out_must_be_after_check_in(): void
    {
        $property = $this->property();
        $buyer = User::factory()->create(['role' => UserRole::Traveler]);

        $this->actingAs($buyer)->post(route('offers.store', $property), [
            'kind' => 'inquiry',
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(9)->toDateString(),
        ])->assertSessionHasErrors('check_out');

        $this->assertSame(0, MemberOffer::query()->count());
    }

    // --- The owner sees every required column ------------------------------

    public function test_the_member_dashboard_shows_the_full_request(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Host]);
        $property = $this->property(['host_id' => $owner->id]);
        $buyer = User::factory()->create(['role' => UserRole::Traveler, 'name' => 'Robin Vale']);

        $this->actingAs($buyer)->post(route('offers.store', $property), [
            'kind' => 'offer',
            'amount_dollars' => '2400',
            'check_in' => now()->addDays(20)->toDateString(),
            'check_out' => now()->addDays(27)->toDateString(),
            'guests' => 5,
            'message' => 'Anniversary trip.',
        ]);

        $this->actingAs($owner)->get(route('offers.index'))->assertOk()
            ->assertSee('Robin Vale')
            ->assertSee($property->title)
            ->assertSee('$2,400.00')
            ->assertSee('Requested dates')
            ->assertSee('Anniversary trip.')
            ->assertSee('ACTIVE');
    }

    // --- Nothing charges a visitor for a stay ------------------------------

    public function test_the_stay_checkout_is_unreachable(): void
    {
        $property = $this->property();
        $buyer = User::factory()->create(['role' => UserRole::Traveler]);

        // The funnel that created a booking and charged a card for the rental.
        $this->actingAs($buyer)
            ->get(route('bookings.review', $property).'?check_in='.now()->addDays(9)->toDateString()
                .'&check_out='.now()->addDays(10)->toDateString().'&guests=2')
            ->assertNotFound();

        $this->actingAs($buyer)
            ->post(route('bookings.store', $property), [
                'check_in' => now()->addDays(9)->toDateString(),
                'check_out' => now()->addDays(10)->toDateString(),
                'guests' => 2,
            ])
            ->assertNotFound();
    }

    public function test_existing_bookings_remain_readable_to_their_owner(): void
    {
        $traveler = User::factory()->create(['role' => UserRole::Traveler]);
        $booking = \App\Models\Booking::factory()->create(['traveler_id' => $traveler->id]);

        // Records already taken must stay visible even though the funnel that
        // created them is closed.
        $this->actingAs($traveler)->get(route('bookings.show', $booking))->assertOk();
        $this->actingAs($traveler)->get(route('bookings.index'))->assertOk();
    }

    /**
     * The checkout code is retained, not deleted, so a future managed-booking
     * arrangement can turn it on. Until an operator does, it does not exist as
     * far as a visitor is concerned.
     */
    public function test_an_operator_can_re_enable_checkout_deliberately(): void
    {
        app(\App\Services\Legal\LegalDocumentRegistry::class)->materialiseAll();
        $this->enableStayCheckout();

        $property = $this->property();
        $buyer = User::factory()->create([
            'role' => UserRole::Traveler,
            'email_verified_at' => now(),
        ]);
        foreach (app(\App\Services\Legal\LegalDocumentRegistry::class)->registrationRequired() as $version) {
            \App\Models\TermsAcceptance::create([
                'user_id' => $buyer->id,
                'terms_version_id' => $version->id,
                'accepted_at' => now(),
            ]);
        }

        $this->actingAs($buyer)
            ->get(route('bookings.review', $property).'?check_in='.now()->addDays(9)->toDateString()
                .'&check_out='.now()->addDays(10)->toDateString().'&guests=2')
            ->assertOk();
    }
}
