<?php

namespace Tests\Feature;

use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Models\PropertyPhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Leaflet + OpenStreetMap on /properties results.
 *
 * The page ships a JSON data island (#vyt-map-data) the JS module reads.
 * We can't render a real map in PHPUnit, but we can prove:
 *   - the JSON island is present with one entry per visible property
 *   - the entry shape matches what the map module expects
 *   - Leaflet CSS + JS + the map module are all loaded
 *   - the layout markup (results column + map column + mobile toggle) is in place
 */
class PropertyMapTest extends TestCase
{
    use RefreshDatabase;

    public function test_results_page_includes_leaflet_css_and_js(): void
    {
        Property::factory()->create(['status' => PropertyStatus::Active->value]);

        $body = $this->get('/properties')->assertOk()->getContent();

        $this->assertStringContainsString('leaflet@1.9.4/dist/leaflet.css', $body);
        $this->assertStringContainsString('leaflet@1.9.4/dist/leaflet.js', $body);
        $this->assertStringContainsString('/vyt-properties-map.js', $body);
    }

    public function test_results_page_renders_map_data_island_with_property_shape(): void
    {
        $property = Property::factory()->create([
            'status'             => PropertyStatus::Active->value,
            'title'              => 'Map Test Villa',
            'city'               => 'Lisbon',
            'country'            => 'PT',
            'latitude'           => 38.7223,
            'longitude'          => -9.1393,
            'price_cents' => 18000,
        ]);
        PropertyPhoto::create([
            'property_id' => $property->id,
            'url'         => 'https://example.test/photo.jpg',
            'sort_order'  => 0,
        ]);

        $body = $this->get('/properties')->assertOk()->getContent();

        // Data island present and id'd correctly.
        $this->assertStringContainsString('id="vyt-map-data"', $body);

        // Extract and parse the JSON island.
        preg_match('#<script id="vyt-map-data" type="application/json">([^<]+)</script>#', $body, $m);
        $this->assertNotEmpty($m, 'Map data island must be parseable.');
        $data = json_decode($m[1], true);
        $this->assertIsArray($data);
        $this->assertCount(1, $data);

        $row = $data[0];
        $this->assertSame($property->id, $row['id']);
        $this->assertSame('Map Test Villa', $row['title']);
        $this->assertSame('Lisbon', $row['city']);
        $this->assertSame('PT', $row['country']);
        $this->assertEqualsWithDelta(38.7223, $row['lat'], 0.0001);
        $this->assertEqualsWithDelta(-9.1393, $row['lng'], 0.0001);
        $this->assertSame(18000, $row['price']);
        $this->assertSame('https://example.test/photo.jpg', $row['photo']);
        $this->assertStringContainsString('/properties/'.$property->id, $row['url']);
    }

    public function test_results_page_renders_map_column_and_mobile_toggle(): void
    {
        Property::factory()->create(['status' => PropertyStatus::Active->value]);

        $body = $this->get('/properties')->assertOk()->getContent();

        $this->assertStringContainsString('id="vyt-properties-map"', $body);
        $this->assertStringContainsString('data-vyt-map-col', $body);
        $this->assertStringContainsString('data-vyt-map-toggle', $body);
        $this->assertStringContainsString('Show map', $body);
    }

    public function test_results_page_omits_map_when_no_results(): void
    {
        // Empty result — index returns the 'No matches' empty state, no map.
        $body = $this->get('/properties?q=zzznoresults')->assertOk()->getContent();

        $this->assertStringNotContainsString('id="vyt-properties-map"', $body);
        $this->assertStringNotContainsString('id="vyt-map-data"', $body);
        $this->assertStringContainsString('No matches', $body);
    }

    public function test_card_carries_data_property_id_for_map_hover_pairing(): void
    {
        $property = Property::factory()->create([
            'status' => PropertyStatus::Active->value,
            'title'  => 'Hoverable Listing',
        ]);

        $body = $this->get('/properties')->assertOk()->getContent();

        // Card carries data-property-id matching the JSON island so the JS
        // module can pair pin ↔ card on hover.
        $this->assertMatchesRegularExpression(
            '/data-property-id="'.$property->id.'"/',
            $body,
        );
    }

    public function test_map_module_has_expected_globals(): void
    {
        $path = public_path('vyt-properties-map.js');
        $this->assertFileExists($path);
        $contents = file_get_contents($path);

        $this->assertStringContainsString('vyt-map-data', $contents);
        $this->assertStringContainsString('vyt-price-pin', $contents);
        $this->assertStringContainsString('openstreetmap.org', $contents);
        $this->assertStringContainsString('vyt:destination-selected', $contents);
    }
}
