<?php

namespace Tests\Feature\Properties;

use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Models\PropertyPhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyBrowseTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_renders_with_title_and_filter_form(): void
    {
        Property::factory()->count(3)->create(['status' => PropertyStatus::Active->value]);

        $resp = $this->get('/properties');

        $resp->assertOk();
        $resp->assertSee('Find your next stay');
        $resp->assertSee('Search', false); // submit button text
        $resp->assertSee('Guests');
    }

    public function test_index_returns_only_active_listings(): void
    {
        Property::factory()->create([
            'title' => 'Active Listing',
            'status' => PropertyStatus::Active->value,
        ]);
        Property::factory()->create([
            'title' => 'Draft Listing',
            'status' => PropertyStatus::Draft->value,
        ]);

        $body = $this->get('/properties')->assertOk()->getContent();

        $this->assertStringContainsString('Active Listing', $body);
        $this->assertStringNotContainsString('Draft Listing', $body);
    }

    public function test_destination_filter_narrows_to_matching_city(): void
    {
        Property::factory()->create([
            'title'  => 'Bali Villa',
            'city'   => 'Bali',
            'status' => PropertyStatus::Active->value,
        ]);
        Property::factory()->create([
            'title'  => 'Paris Apartment',
            'city'   => 'Paris',
            'status' => PropertyStatus::Active->value,
        ]);

        $body = $this->get('/properties?destination=bali')->assertOk()->getContent();

        $this->assertStringContainsString('Bali Villa', $body);
        $this->assertStringNotContainsString('Paris Apartment', $body);
        $this->assertStringContainsString('Stays in Bali', $body);
    }

    public function test_destination_filter_handles_kebab_case_slugs(): void
    {
        Property::factory()->create([
            'title'  => 'Tahoe Cabin',
            'city'   => 'Lake Tahoe',
            'status' => PropertyStatus::Active->value,
        ]);

        $body = $this->get('/properties?destination=lake-tahoe')->assertOk()->getContent();

        $this->assertStringContainsString('Tahoe Cabin', $body);
        $this->assertStringContainsString('Stays in Lake Tahoe', $body);
    }

    public function test_query_filter_matches_title_or_city(): void
    {
        Property::factory()->create([
            'title' => 'Beachfront Bungalow',
            'city'  => 'Bali',
            'status' => PropertyStatus::Active->value,
        ]);
        Property::factory()->create([
            'title' => 'Mountain Chalet',
            'city'  => 'Aspen',
            'status' => PropertyStatus::Active->value,
        ]);

        $body = $this->get('/properties?q=beachfront')->assertOk()->getContent();

        $this->assertStringContainsString('Beachfront Bungalow', $body);
        $this->assertStringNotContainsString('Mountain Chalet', $body);
    }

    public function test_capacity_filter_excludes_smaller_listings(): void
    {
        Property::factory()->create([
            'title'    => 'Small Studio',
            'capacity' => 2,
            'status'   => PropertyStatus::Active->value,
        ]);
        Property::factory()->create([
            'title'    => 'Big Villa',
            'capacity' => 8,
            'status'   => PropertyStatus::Active->value,
        ]);

        $body = $this->get('/properties?min_capacity=6')->assertOk()->getContent();

        $this->assertStringContainsString('Big Villa', $body);
        $this->assertStringNotContainsString('Small Studio', $body);
    }

    public function test_max_price_filter_converts_dollars_to_cents(): void
    {
        Property::factory()->create([
            'title'              => 'Budget',
            'base_nightly_cents' => 8000,
            'status'             => PropertyStatus::Active->value,
        ]);
        Property::factory()->create([
            'title'              => 'Luxury',
            'base_nightly_cents' => 50000,
            'status'             => PropertyStatus::Active->value,
        ]);

        $body = $this->get('/properties?max_price=200')->assertOk()->getContent();

        $this->assertStringContainsString('Budget', $body);
        $this->assertStringNotContainsString('Luxury', $body);
    }

    public function test_show_renders_active_property(): void
    {
        $property = Property::factory()->create([
            'title'       => 'Test Villa',
            'description' => 'A lovely place to stay near the water.',
            'status'      => PropertyStatus::Active->value,
        ]);

        $resp = $this->get(route('properties.show', $property));

        $resp->assertOk();
        $resp->assertSee('Test Villa');
        $resp->assertSee('A lovely place to stay near the water.');
        // Booking date-picker CTA replaced the old disabled "Request to book" button.
        $resp->assertSee('Continue to review');
    }

    public function test_show_404s_inactive_property(): void
    {
        $property = Property::factory()->create(['status' => PropertyStatus::Draft->value]);

        $this->get(route('properties.show', $property))->assertNotFound();
    }

    public function test_show_displays_photos_when_present(): void
    {
        $property = Property::factory()->create(['status' => PropertyStatus::Active->value]);
        PropertyPhoto::create([
            'property_id' => $property->id,
            'url'         => 'https://example.test/photo1.jpg',
            'sort_order'  => 0,
        ]);

        $body = $this->get(route('properties.show', $property))->assertOk()->getContent();

        $this->assertStringContainsString('https://example.test/photo1.jpg', $body);
    }

    public function test_landing_destination_cards_link_to_filtered_index(): void
    {
        $body = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('/properties?destination=bali', $body);
        $this->assertStringContainsString('/properties?destination=tokyo', $body);
        $this->assertStringContainsString('/properties?destination=lake-tahoe', $body);
    }

    public function test_landing_search_form_submits_to_properties_index(): void
    {
        $body = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<form class="search" action="[^"]*\/properties"/', $body);
    }
}
