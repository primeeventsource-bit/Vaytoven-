<?php

namespace Tests\Feature\Listings;

use App\Enums\AvailabilityWeekStatus;
use App\Enums\PropertyStatus;
use App\Enums\UserRole;
use App\Models\MemberOffer;
use App\Models\Property;
use App\Models\PropertyAvailabilityWeek;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An offer names the advertised week it is for.
 *
 * Offers used to carry a property and a pair of dates the visitor typed, with
 * nothing tying them to the time actually on sale. A member with three weeks
 * listed had to match offers up by eye, and nothing stopped an offer arriving
 * for dates that were never advertised.
 */
class OfferWeekBindingTest extends TestCase
{
    use RefreshDatabase;

    private function listing(): Property
    {
        return Property::factory()->create([
            'host_id' => User::factory()->create()->id,
            'status'  => PropertyStatus::Active->value,
            'allow_offers' => true,
        ]);
    }

    private function week(Property $property, ?AvailabilityWeekStatus $status = null): PropertyAvailabilityWeek
    {
        return PropertyAvailabilityWeek::create([
            'property_id' => $property->id,
            'starts_on'   => now()->addMonth()->toDateString(),
            'ends_on'     => now()->addMonth()->addDays(7)->toDateString(),
            'status'      => ($status ?? AvailabilityWeekStatus::Available)->value,
        ]);
    }

    private function buyer(): User
    {
        return User::factory()->create(['must_change_password' => false]);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'kind'           => 'offer',
            'amount_dollars' => '1800',
            'message'        => 'Interested in this week for our family.',
            'check_in'       => now()->addMonth()->toDateString(),
            'check_out'      => now()->addMonth()->addDays(7)->toDateString(),
            'guests'         => 4,
        ], $overrides);
    }

    public function test_an_offer_records_the_week_it_is_for(): void
    {
        $property = $this->listing();
        $week     = $this->week($property);

        $this->actingAs($this->buyer())
            ->post(route('offers.store', $property), $this->payload([
                'availability_week_id' => $week->id,
            ]));

        $offer = MemberOffer::sole();

        $this->assertSame($week->id, $offer->availability_week_id);
        $this->assertTrue($offer->availabilityWeek->is($week));
    }

    /**
     * The week stops taking new offers once one arrives, so the member is not
     * left comparing three bids for the same nights that came in while they
     * were deciding. It stays advertised.
     */
    public function test_the_week_moves_to_offer_pending(): void
    {
        $property = $this->listing();
        $week     = $this->week($property);

        $this->actingAs($this->buyer())
            ->post(route('offers.store', $property), $this->payload([
                'availability_week_id' => $week->id,
            ]));

        $week->refresh();

        $this->assertSame(AvailabilityWeekStatus::OfferPending, $week->status);
        $this->assertTrue($week->status->isPublic(), 'it should still be advertised');
        $this->assertFalse($week->status->acceptsOffers());
    }

    /** A week id from another listing must not be postable through this form. */
    public function test_a_week_from_another_property_is_refused(): void
    {
        $property = $this->listing();
        $foreign  = $this->week($this->listing());

        $this->actingAs($this->buyer())
            ->post(route('offers.store', $property), $this->payload([
                'availability_week_id' => $foreign->id,
            ]))
            ->assertSessionHasErrors('availability_week_id');

        $this->assertSame(0, MemberOffer::count());
    }

    public function test_a_week_already_under_offer_is_refused(): void
    {
        $property = $this->listing();
        $week     = $this->week($property, AvailabilityWeekStatus::OfferPending);

        $this->actingAs($this->buyer())
            ->post(route('offers.store', $property), $this->payload([
                'availability_week_id' => $week->id,
            ]))
            ->assertSessionHasErrors('availability_week_id');

        $this->assertSame(0, MemberOffer::count());
    }

    /** An inquiry that is a question rather than a bid has no week. */
    public function test_an_offer_without_a_week_is_still_accepted(): void
    {
        $property = $this->listing();

        $this->actingAs($this->buyer())
            ->post(route('offers.store', $property), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertNull(MemberOffer::sole()->availability_week_id);
    }

    /**
     * Removing a week from the calendar must not delete the offers made
     * against it: the offer still happened and the member may still be
     * corresponding about it.
     */
    public function test_deleting_the_week_keeps_the_offer(): void
    {
        $property = $this->listing();
        $week     = $this->week($property);

        $this->actingAs($this->buyer())
            ->post(route('offers.store', $property), $this->payload([
                'availability_week_id' => $week->id,
            ]));

        $week->delete();

        $offer = MemberOffer::sole();

        $this->assertNull($offer->availability_week_id);
        $this->assertSame($property->id, $offer->property_id);
    }

    // --- the form ----------------------------------------------------------------

    public function test_the_form_offers_the_open_weeks(): void
    {
        $property = $this->listing();
        $this->week($property);

        $this->actingAs($this->buyer())
            ->get(route('properties.show', $property))
            ->assertOk()
            ->assertSee('Which week')
            ->assertSee('name="availability_week_id"', false);
    }

    /** A week nobody can offer on should not be presented as a choice. */
    public function test_a_closed_week_is_not_offered_in_the_form(): void
    {
        $property = $this->listing();
        $this->week($property, AvailabilityWeekStatus::Closed);

        $this->actingAs($this->buyer())
            ->get(route('properties.show', $property))
            ->assertOk()
            ->assertDontSee('name="availability_week_id"', false);
    }
}
