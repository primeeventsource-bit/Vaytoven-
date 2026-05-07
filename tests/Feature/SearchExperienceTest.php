<?php

namespace Tests\Feature;

use App\Enums\PropertyStatus;
use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_renders_new_search_bar_with_three_fields(): void
    {
        $body = $this->get('/')->assertOk()->getContent();

        // The new bar has data-vyt-search on the form + the three fields.
        $this->assertStringContainsString('data-vyt-search', $body);
        $this->assertStringContainsString('data-vyt-search-input', $body);
        $this->assertStringContainsString('data-vyt-dates-trigger', $body);
        $this->assertStringContainsString('data-vyt-guests-trigger', $body);
        $this->assertStringContainsString('Search destinations', $body);
        $this->assertStringContainsString('/vyt-search.js', $body);
    }

    public function test_properties_index_shows_compact_search_bar_at_top(): void
    {
        $body = $this->get('/properties?q=Bali')->assertOk()->getContent();

        // Compact variant + prefilled with current query.
        $this->assertStringContainsString('vyt-search is-compact', $body);
        $this->assertStringContainsString('value="Bali"', $body);
    }

    public function test_destination_suggest_returns_cities_for_matching_query(): void
    {
        Property::factory()->count(2)->create([
            'status' => PropertyStatus::Active->value,
            'city'   => 'Bali',
            'country' => 'ID',
            'latitude' => -8.5,
            'longitude' => 115.2,
        ]);
        Property::factory()->create([
            'status' => PropertyStatus::Active->value,
            'city'   => 'Paris',
            'country' => 'FR',
        ]);

        $resp = $this->getJson('/api/v1/destinations/suggest?q=bali');
        $resp->assertOk();

        $resp->assertJsonStructure(['query', 'suggestions' => [['type', 'label', 'sublabel', 'value', 'lat', 'lng', 'zoom']]]);

        $payload = $resp->json();
        $this->assertSame('bali', $payload['query']);

        $cityHit = collect($payload['suggestions'])->firstWhere('type', 'city');
        $this->assertNotNull($cityHit);
        $this->assertSame('Bali', $cityHit['label']);
        $this->assertEqualsWithDelta(-8.5, $cityHit['lat'], 0.01);
        $this->assertEqualsWithDelta(115.2, $cityHit['lng'], 0.01);
        $this->assertSame(11, $cityHit['zoom']);
    }

    public function test_destination_suggest_returns_property_matches_with_id(): void
    {
        $property = Property::factory()->create([
            'status' => PropertyStatus::Active->value,
            'title'  => 'Beachfront Cliff Villa',
            'city'   => 'Lima',
            'country' => 'PE',
        ]);

        $resp = $this->getJson('/api/v1/destinations/suggest?q=cliff');
        $resp->assertOk();

        $propertyHit = collect($resp->json('suggestions'))->firstWhere('type', 'property');
        $this->assertNotNull($propertyHit);
        $this->assertSame($property->id, $propertyHit['property_id']);
        $this->assertSame(14, $propertyHit['zoom']);
    }

    public function test_destination_suggest_returns_empty_for_short_query(): void
    {
        Property::factory()->create(['status' => PropertyStatus::Active->value, 'city' => 'Bali']);

        $resp = $this->getJson('/api/v1/destinations/suggest?q=b');
        $resp->assertOk()->assertJson(['query' => 'b', 'suggestions' => []]);
    }

    public function test_destination_suggest_skips_inactive_listings(): void
    {
        Property::factory()->create([
            'status' => PropertyStatus::Draft->value,
            'city'   => 'Hidden',
        ]);

        $resp = $this->getJson('/api/v1/destinations/suggest?q=hidden');
        $resp->assertOk();
        $this->assertCount(0, $resp->json('suggestions'));
    }

    public function test_search_submission_with_adults_filters_by_capacity(): void
    {
        Property::factory()->create([
            'status'   => PropertyStatus::Active->value,
            'title'    => 'Cozy Studio',
            'capacity' => 2,
        ]);
        Property::factory()->create([
            'status'   => PropertyStatus::Active->value,
            'title'    => 'Family Villa',
            'capacity' => 8,
        ]);

        $body = $this->get('/properties?adults=4&children=2')->assertOk()->getContent();

        // total guests = 6 → capacity ≥ 6 → only Family Villa qualifies.
        $this->assertStringContainsString('Family Villa', $body);
        $this->assertStringNotContainsString('Cozy Studio', $body);
    }

    public function test_search_submission_with_children_only_filters_correctly(): void
    {
        Property::factory()->create([
            'status'   => PropertyStatus::Active->value,
            'title'    => 'Tiny Cabin',
            'capacity' => 2,
        ]);
        Property::factory()->create([
            'status'   => PropertyStatus::Active->value,
            'title'    => 'Roomy House',
            'capacity' => 6,
        ]);

        // adults=2 + children=3 = 5 guests total; only 6-capacity matches.
        $body = $this->get('/properties?adults=2&children=3')->assertOk()->getContent();

        $this->assertStringContainsString('Roomy House', $body);
        $this->assertStringNotContainsString('Tiny Cabin', $body);
    }

    public function test_legacy_min_capacity_param_still_works(): void
    {
        Property::factory()->create(['status' => PropertyStatus::Active->value, 'title' => 'Big Place', 'capacity' => 8]);
        Property::factory()->create(['status' => PropertyStatus::Active->value, 'title' => 'Small Place', 'capacity' => 2]);

        $body = $this->get('/properties?min_capacity=6')->assertOk()->getContent();

        $this->assertStringContainsString('Big Place', $body);
        $this->assertStringNotContainsString('Small Place', $body);
    }

    public function test_search_bar_styles_loaded_on_landing(): void
    {
        $body = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('.vyt-search', $body);
        $this->assertStringContainsString('vyt-search-suggest', $body);
        $this->assertStringContainsString('vyt-cal', $body);
    }

    public function test_search_js_file_exists_with_expected_globals(): void
    {
        // Static asset isn't routed through Laravel — read from disk.
        $path = public_path('vyt-search.js');
        $this->assertFileExists($path);

        $contents = file_get_contents($path);
        $this->assertStringContainsString('SUGGEST_ENDPOINT', $contents);
        $this->assertStringContainsString('/api/v1/destinations/suggest', $contents);
        $this->assertStringContainsString('vyt-search', $contents);
    }
}
