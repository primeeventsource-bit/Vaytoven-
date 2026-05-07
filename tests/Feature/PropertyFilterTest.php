<?php

namespace Tests\Feature;

use App\Enums\PropertyStatus;
use App\Models\Amenity;
use App\Models\Property;
use Database\Seeders\AmenitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Filter expansion: price range (min/max in dollars) + amenity multi-select.
 * Amenities require AND-of-all so 'pool + wifi' returns only properties
 * that carry both.
 */
class PropertyFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AmenitiesSeeder::class);
    }

    public function test_min_price_filter_excludes_cheaper_listings(): void
    {
        Property::factory()->create([
            'status' => PropertyStatus::Active->value,
            'title'  => 'Budget Studio',
            'base_nightly_cents' => 5000,
        ]);
        Property::factory()->create([
            'status' => PropertyStatus::Active->value,
            'title'  => 'Premium Suite',
            'base_nightly_cents' => 25000,
        ]);

        $body = $this->get('/properties?min_price=200')->assertOk()->getContent();

        $this->assertStringContainsString('Premium Suite', $body);
        $this->assertStringNotContainsString('Budget Studio', $body);
    }

    public function test_combined_min_and_max_price_filter_narrows_to_band(): void
    {
        Property::factory()->create(['status' => PropertyStatus::Active->value, 'title' => 'Cheap',  'base_nightly_cents' => 5000]);
        Property::factory()->create(['status' => PropertyStatus::Active->value, 'title' => 'In Band','base_nightly_cents' => 18000]);
        Property::factory()->create(['status' => PropertyStatus::Active->value, 'title' => 'Spendy', 'base_nightly_cents' => 50000]);

        $body = $this->get('/properties?min_price=100&max_price=300')->assertOk()->getContent();

        $this->assertStringContainsString('In Band', $body);
        $this->assertStringNotContainsString('Cheap', $body);
        $this->assertStringNotContainsString('Spendy', $body);
    }

    public function test_amenity_filter_requires_all_selected_amenities(): void
    {
        $wifi = Amenity::where('slug', 'wifi')->first();
        $pool = Amenity::where('slug', 'pool')->first();
        $petsAllowed = Amenity::where('slug', 'pets-allowed')->first();

        $wifiOnly = Property::factory()->create(['status' => PropertyStatus::Active->value, 'title' => 'Wifi Only Cabin']);
        $wifiOnly->amenities()->sync([$wifi->id]);

        $wifiAndPool = Property::factory()->create(['status' => PropertyStatus::Active->value, 'title' => 'Wifi And Pool']);
        $wifiAndPool->amenities()->sync([$wifi->id, $pool->id]);

        $allThree = Property::factory()->create(['status' => PropertyStatus::Active->value, 'title' => 'Wifi Pool Pets']);
        $allThree->amenities()->sync([$wifi->id, $pool->id, $petsAllowed->id]);

        // Wifi alone — all three should appear.
        $body = $this->get('/properties?amenities[]=wifi')->assertOk()->getContent();
        $this->assertStringContainsString('Wifi Only Cabin', $body);
        $this->assertStringContainsString('Wifi And Pool', $body);
        $this->assertStringContainsString('Wifi Pool Pets', $body);

        // Wifi + Pool — drop the pool-less one.
        $body = $this->get('/properties?amenities[]=wifi&amenities[]=pool')->assertOk()->getContent();
        $this->assertStringNotContainsString('Wifi Only Cabin', $body);
        $this->assertStringContainsString('Wifi And Pool', $body);
        $this->assertStringContainsString('Wifi Pool Pets', $body);

        // Wifi + Pool + Pets — only the all-three property survives.
        $body = $this->get('/properties?amenities[]=wifi&amenities[]=pool&amenities[]=pets-allowed')->assertOk()->getContent();
        $this->assertStringNotContainsString('Wifi Only Cabin', $body);
        $this->assertStringNotContainsString('Wifi And Pool', $body);
        $this->assertStringContainsString('Wifi Pool Pets', $body);
    }

    public function test_unknown_amenity_slug_returns_zero_results(): void
    {
        Property::factory()->count(3)->create(['status' => PropertyStatus::Active->value]);

        $body = $this->get('/properties?amenities[]=does-not-exist')->assertOk()->getContent();
        $this->assertStringContainsString('No matches', $body);
    }

    public function test_filter_rail_renders_with_curated_amenity_chips(): void
    {
        Property::factory()->create(['status' => PropertyStatus::Active->value]);

        $body = $this->get('/properties')->assertOk()->getContent();

        $this->assertStringContainsString('class="props-filter-rail"', $body);
        $this->assertStringContainsString('Apply filters', $body);
        // Curated chips are present.
        $this->assertStringContainsString('value="wifi"', $body);
        $this->assertStringContainsString('value="pool"', $body);
        $this->assertStringContainsString('value="beachfront"', $body);
        $this->assertStringContainsString('value="pets-allowed"', $body);
    }

    public function test_filter_rail_marks_selected_amenity_chip_as_on(): void
    {
        Property::factory()->create(['status' => PropertyStatus::Active->value]);

        $body = $this->get('/properties?amenities[]=pool')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<label class="props-amenity-chip is-on">[^<]*<input[^>]*value="pool"[^>]*checked/s',
            $body,
        );
    }

    public function test_amenity_chip_in_active_filters_links_to_remove_just_that_amenity(): void
    {
        Property::factory()->create(['status' => PropertyStatus::Active->value]);

        $body = $this->get('/properties?amenities[]=pool&amenities[]=wifi')->assertOk()->getContent();

        // Chip "× link" for pool removes pool but keeps wifi.
        $this->assertStringContainsString('class="props-active-filters"', $body);
        $this->assertMatchesRegularExpression('/Pool[\s\S]*?amenities%5B0%5D=wifi/s', $body);
    }

    public function test_filters_carry_existing_destination_and_query_through(): void
    {
        Property::factory()->create([
            'status' => PropertyStatus::Active->value,
            'title' => 'Bali Pool Villa',
            'city' => 'Bali',
        ])->amenities()->sync([Amenity::where('slug', 'pool')->first()->id]);

        $body = $this->get('/properties?destination=bali&amenities[]=pool')->assertOk()->getContent();

        $this->assertStringContainsString('Stays in Bali', $body);
        $this->assertStringContainsString('Bali Pool Villa', $body);
        // Hidden inputs in the form preserve destination so 'Apply filters' doesn't lose it.
        $this->assertMatchesRegularExpression('/<input type="hidden" name="destination" value="bali"/', $body);
    }

    public function test_existing_min_capacity_logic_unchanged_by_filter_addition(): void
    {
        Property::factory()->create(['status' => PropertyStatus::Active->value, 'title' => 'Big',   'capacity' => 8]);
        Property::factory()->create(['status' => PropertyStatus::Active->value, 'title' => 'Small', 'capacity' => 2]);

        $body = $this->get('/properties?min_capacity=6')->assertOk()->getContent();
        $this->assertStringContainsString('Big', $body);
        $this->assertStringNotContainsString('Small', $body);
    }
}
