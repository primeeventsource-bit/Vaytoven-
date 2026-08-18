<?php

namespace Tests\Feature\Listings;

use App\Enums\AvailabilityWeekStatus;
use App\Models\Property;
use App\Models\PropertyAvailabilityWeek;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyListingFieldsTest extends TestCase
{
    use RefreshDatabase;

    /** The en dash the date labels use, written as an escape so the file stays ASCII. */
    private const EN_DASH = "\u{2013}";

    private function property(array $attributes = []): Property
    {
        return Property::factory()->create(array_merge([
            'host_id' => User::factory()->create()->id,
        ], $attributes));
    }

    /**
     * A column missing from $fillable is discarded in silence — the failure
     * that made staff_notes look like it saved when it did not.
     */
    public function test_every_builder_field_actually_persists(): void
    {
        $property = $this->property();

        $values = [
            'property_kind'            => 'villa',
            'resort_name'              => 'Ko Olina Beach Club',
            'position_in_package'      => 2,
            'location_precision'       => 'exact',
            'square_feet'              => 1250,
            'floor_unit'               => 'Tower 2, unit 1408',
            'bed_configuration'        => '1 king, 2 queens, 1 sofa bed',
            'check_in_day'             => 'Saturday',
            'check_in_time'            => '16:00',
            'check_out_time'           => '10:00',
            'unit_size_type'           => '2-bedroom lockoff',
            'view_type'                => 'ocean',
            'accessibility_notes'      => 'Step-free access from the lobby.',
            'pet_policy'               => 'No pets',
            'smoking_policy'           => 'Non-smoking',
            'parking_info'             => 'Self-park included',
            'headline'                 => 'Spacious Two-Bedroom Resort Stay',
            'short_description'        => 'Two bedrooms, resort pool, full kitchen.',
            'highlights'               => ['Sleeps up to 6', 'Resort pool', 'Full kitchen'],
            'allow_offers'             => false,
            'display_suggested_amount' => true,
            'minimum_offer_cents'      => 120000,
        ];

        $property->update($values);
        $property->refresh();

        foreach ($values as $field => $expected) {
            $this->assertSame($expected, $property->{$field}, "{$field} did not persist");
        }
    }

    public function test_a_reference_is_assigned_to_every_property(): void
    {
        $this->assertMatchesRegularExpression('/^VAY-P-\d{5,}$/', $this->property()->reference);
    }

    public function test_references_are_unique(): void
    {
        $this->assertNotSame($this->property()->reference, $this->property()->reference);
    }

    // --- location precision ---------------------------------------------------

    /**
     * A member advertising a home they still live in has a real interest in
     * the pin not being their front door.
     */
    public function test_approximate_is_the_default_and_rounds_the_pin(): void
    {
        $property = $this->property(['latitude' => 28.385233, 'longitude' => -81.563873]);

        $this->assertSame('approximate', $property->location_precision);
        $this->assertSame(['lat' => 28.39, 'lng' => -81.56], $property->publicCoordinates());
    }

    public function test_exact_is_honoured_when_chosen(): void
    {
        $property = $this->property([
            'latitude' => 28.385233, 'longitude' => -81.563873, 'location_precision' => 'exact',
        ]);

        $this->assertSame(['lat' => 28.385233, 'lng' => -81.563873], $property->publicCoordinates());
    }

    public function test_city_only_publishes_no_pin_at_all(): void
    {
        $property = $this->property([
            'latitude' => 28.385233, 'longitude' => -81.563873, 'location_precision' => 'city_only',
        ]);

        $this->assertNull($property->publicCoordinates());
    }

    public function test_a_property_with_no_coordinates_publishes_no_pin(): void
    {
        $this->assertNull($this->property(['latitude' => null, 'longitude' => null])->publicCoordinates());
    }

    // --- availability weeks ---------------------------------------------------

    public function test_a_week_reads_back_as_dates_not_a_week_number(): void
    {
        $week = PropertyAvailabilityWeek::create([
            'property_id' => $this->property()->id,
            'starts_on'   => '2026-09-05',
            'ends_on'     => '2026-09-12',
        ]);

        $this->assertSame('September 5'.self::EN_DASH.'12, 2026', $week->label());
        $this->assertSame(7, $week->nights());
        $this->assertSame(AvailabilityWeekStatus::Available, $week->status);
    }

    public function test_a_week_spanning_two_years_reads_correctly(): void
    {
        $week = PropertyAvailabilityWeek::create([
            'property_id' => $this->property()->id,
            'starts_on'   => '2026-12-28',
            'ends_on'     => '2027-01-04',
        ]);

        $this->assertSame('December 28, 2026 '.self::EN_DASH.' January 4, 2027', $week->label());
    }

    /** A week already under offer stays visible but stops taking new ones. */
    public function test_offer_pending_is_public_but_closed_to_new_offers(): void
    {
        $this->assertTrue(AvailabilityWeekStatus::OfferPending->isPublic());
        $this->assertFalse(AvailabilityWeekStatus::OfferPending->acceptsOffers());
        $this->assertTrue(AvailabilityWeekStatus::Available->acceptsOffers());
        $this->assertFalse(AvailabilityWeekStatus::Unavailable->isPublic());
        $this->assertFalse(AvailabilityWeekStatus::Closed->isPublic());
    }

    public function test_only_current_public_weeks_are_visible(): void
    {
        $property = $this->property();

        $live = PropertyAvailabilityWeek::create([
            'property_id' => $property->id,
            'starts_on'   => now()->addWeek()->toDateString(),
            'ends_on'     => now()->addWeeks(2)->toDateString(),
        ]);

        PropertyAvailabilityWeek::create([
            'property_id' => $property->id,
            'starts_on'   => now()->subWeeks(4)->toDateString(),
            'ends_on'     => now()->subWeeks(3)->toDateString(),
        ]);

        PropertyAvailabilityWeek::create([
            'property_id' => $property->id,
            'starts_on'   => now()->addWeeks(6)->toDateString(),
            'ends_on'     => now()->addWeeks(7)->toDateString(),
            'status'      => AvailabilityWeekStatus::Unavailable,
        ]);

        $visible = PropertyAvailabilityWeek::publiclyVisible()->get();

        $this->assertCount(1, $visible);
        $this->assertTrue($visible->first()->is($live));
    }

    /** Two rows for one week give staff two places to set a status that must agree. */
    public function test_the_same_week_cannot_be_listed_twice(): void
    {
        $property = $this->property();

        PropertyAvailabilityWeek::create([
            'property_id' => $property->id, 'starts_on' => '2026-09-05', 'ends_on' => '2026-09-12',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        PropertyAvailabilityWeek::create([
            'property_id' => $property->id, 'starts_on' => '2026-09-05', 'ends_on' => '2026-09-12',
        ]);
    }
}
