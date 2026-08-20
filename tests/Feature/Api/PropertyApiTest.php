<?php

namespace Tests\Feature\Api;

use App\Enums\PropertyStatus;
use App\Models\Amenity;
use App\Models\Property;
use App\Models\PropertyPhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_only_active_properties(): void
    {
        Property::factory()->count(3)->create(['status' => PropertyStatus::Active->value]);
        Property::factory()->draft()->count(2)->create();

        $resp = $this->getJson('/api/v1/properties');

        $resp->assertOk();
        $this->assertCount(3, $resp->json('data'));
    }

    public function test_index_supports_text_search_on_title(): void
    {
        Property::factory()->create([
            'title' => 'Stunning beachfront cottage',
            'status' => PropertyStatus::Active->value,
        ]);
        Property::factory()->create([
            'title' => 'Mountain cabin retreat',
            'status' => PropertyStatus::Active->value,
        ]);

        $resp = $this->getJson('/api/v1/properties?q=beachfront');

        $resp->assertOk();
        $this->assertCount(1, $resp->json('data'));
        $this->assertSame('Stunning beachfront cottage', $resp->json('data.0.title'));
    }

    public function test_index_filters_by_country_min_capacity_max_price(): void
    {
        Property::factory()->create([
            'country' => 'US', 'capacity' => 6, 'price_cents' => 12000,
            'status' => PropertyStatus::Active->value,
        ]);
        Property::factory()->create([
            'country' => 'CA', 'capacity' => 6, 'price_cents' => 12000,
            'status' => PropertyStatus::Active->value,
        ]);
        Property::factory()->create([
            'country' => 'US', 'capacity' => 2, 'price_cents' => 12000,
            'status' => PropertyStatus::Active->value,
        ]);
        Property::factory()->create([
            'country' => 'US', 'capacity' => 6, 'price_cents' => 50000,
            'status' => PropertyStatus::Active->value,
        ]);

        $resp = $this->getJson('/api/v1/properties?country=US&min_capacity=4&max_price_cents=20000');

        $resp->assertOk();
        $this->assertCount(1, $resp->json('data'));
    }

    public function test_index_supports_bounding_box_geo_filter(): void
    {
        // Inside box (NYC-ish)
        Property::factory()->create([
            'latitude' => 40.7128, 'longitude' => -74.0060,
            'status' => PropertyStatus::Active->value,
        ]);
        // Outside box (LA)
        Property::factory()->create([
            'latitude' => 34.0522, 'longitude' => -118.2437,
            'status' => PropertyStatus::Active->value,
        ]);

        $resp = $this->getJson('/api/v1/properties?lat_min=40&lat_max=41&lng_min=-75&lng_max=-73');

        $resp->assertOk();
        $this->assertCount(1, $resp->json('data'));
    }

    public function test_index_paginates(): void
    {
        Property::factory()->count(25)->create(['status' => PropertyStatus::Active->value]);

        $resp = $this->getJson('/api/v1/properties?per_page=10');

        $resp->assertOk();
        $this->assertCount(10, $resp->json('data'));
        $this->assertSame(25, $resp->json('meta.total'));
    }

    public function test_show_returns_active_property_with_relationships(): void
    {
        $property = Property::factory()->create(['status' => PropertyStatus::Active->value]);
        $a = Amenity::factory()->create(['slug' => 'wifi-y']);
        $b = Amenity::factory()->create(['slug' => 'pool-y']);
        $property->amenities()->attach([$a->id, $b->id]);
        PropertyPhoto::factory()->count(2)->create(['property_id' => $property->id]);

        $resp = $this->getJson("/api/v1/properties/{$property->id}");

        $resp->assertOk()
            ->assertJsonPath('data.id', $property->id)
            ->assertJsonCount(2, 'data.amenities')
            ->assertJsonCount(2, 'data.photos');
    }

    public function test_show_returns_404_for_non_active_property(): void
    {
        $property = Property::factory()->draft()->create();

        $this->getJson("/api/v1/properties/{$property->id}")->assertNotFound();
    }

    public function test_money_fields_serialise_as_integer_cents(): void
    {
        $property = Property::factory()->create([
            'price_cents' => 12500,
            'cleaning_fee_cents' => 5000,
            'status' => PropertyStatus::Active->value,
        ]);

        $resp = $this->getJson("/api/v1/properties/{$property->id}");

        $resp->assertOk();
        $this->assertSame(12500, $resp->json('data.price_cents'));
        $this->assertSame(5000, $resp->json('data.cleaning_fee_cents'));
        $this->assertIsInt($resp->json('data.price_cents'));
    }
}
