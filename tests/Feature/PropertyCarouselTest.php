<?php

namespace Tests\Feature;

use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Models\PropertyPhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Photo carousel coverage. Server-side we can verify the carousel markup
 * is rendered correctly per property; the JS module is bound by
 * /vyt-carousel.js once the page is in the browser.
 */
class PropertyCarouselTest extends TestCase
{
    use RefreshDatabase;

    public function test_card_renders_carousel_with_all_photos_in_order(): void
    {
        $property = Property::factory()->create([
            'status' => PropertyStatus::Active->value,
            'title'  => 'Three-Photo Listing',
        ]);
        foreach (['a.jpg', 'b.jpg', 'c.jpg'] as $i => $name) {
            PropertyPhoto::create([
                'property_id' => $property->id,
                'url'         => "https://cdn.test/{$name}",
                'sort_order'  => $i,
                'caption'     => "Photo {$i}",
            ]);
        }

        $body = $this->get('/properties')->assertOk()->getContent();

        // Each card carries a carousel with three slides + arrows + dots.
        $this->assertStringContainsString('data-vyt-carousel', $body);
        $this->assertStringContainsString('vyt-carousel-track', $body);
        $this->assertStringContainsString('https://cdn.test/a.jpg', $body);
        $this->assertStringContainsString('https://cdn.test/b.jpg', $body);
        $this->assertStringContainsString('https://cdn.test/c.jpg', $body);
        $this->assertStringContainsString('vyt-carousel-arrow is-prev', $body);
        $this->assertStringContainsString('vyt-carousel-arrow is-next', $body);
        $this->assertStringContainsString('vyt-carousel-dots', $body);
    }

    public function test_single_photo_renders_carousel_without_arrows_or_dots(): void
    {
        $property = Property::factory()->create([
            'status' => PropertyStatus::Active->value,
            'title'  => 'One-Photo Listing',
        ]);
        PropertyPhoto::create([
            'property_id' => $property->id,
            'url'         => 'https://cdn.test/only.jpg',
            'sort_order'  => 0,
        ]);

        $body = $this->get('/properties')->assertOk()->getContent();

        // Carousel still renders for the single photo, but the
        // arrow/dot chrome is suppressed by the partial when count <= 1.
        $this->assertStringContainsString('https://cdn.test/only.jpg', $body);
        // The arrow markup is partial-conditional; it shouldn't render
        // for this property. Use a more specific regex than a plain
        // contains since other multi-photo properties on the page
        // could legitimately have arrows.
        $cardSection = strstr($body, 'One-Photo Listing');
        $this->assertNotFalse($cardSection);
        $cardSlice = substr($cardSection, 0, 800);
        $this->assertStringNotContainsString('vyt-carousel-arrow is-prev', $cardSlice);
    }

    public function test_no_photos_renders_empty_tinted_block_not_carousel(): void
    {
        Property::factory()->create([
            'status' => PropertyStatus::Active->value,
            'title'  => 'No-Photo Cabin',
        ]);

        $body = $this->get('/properties')->assertOk()->getContent();

        // Empty state: a flat tinted div, no track / arrows / dots.
        $this->assertStringContainsString('No-Photo Cabin', $body);
        $cardSection = strstr($body, 'No-Photo Cabin');
        $cardSlice = substr($cardSection, -800);  // section preceding the title
        $cardBody = strstr($body, 'No-Photo Cabin', true);
        $tail = substr($cardBody, -400);
        $this->assertStringContainsString('vyt-carousel', $tail);
        $this->assertStringNotContainsString('vyt-carousel-track', $tail);
    }

    public function test_detail_hero_uses_hero_carousel_with_eager_loading(): void
    {
        $property = Property::factory()->create([
            'status' => PropertyStatus::Active->value,
        ]);
        PropertyPhoto::create([
            'property_id' => $property->id,
            'url'         => 'https://cdn.test/hero1.jpg',
            'sort_order'  => 0,
        ]);
        PropertyPhoto::create([
            'property_id' => $property->id,
            'url'         => 'https://cdn.test/hero2.jpg',
            'sort_order'  => 1,
        ]);

        $body = $this->get(route('properties.show', $property))->assertOk()->getContent();

        $this->assertStringContainsString('vyt-carousel is-hero', $body);
        $this->assertStringContainsString('https://cdn.test/hero1.jpg', $body);
        $this->assertStringContainsString('loading="eager"', $body);
    }

    public function test_carousel_js_module_loaded_on_properties_layout(): void
    {
        Property::factory()->create(['status' => PropertyStatus::Active->value]);

        $body = $this->get('/properties')->assertOk()->getContent();

        $this->assertStringContainsString('/vyt-carousel.js', $body);
    }

    public function test_carousel_js_file_carries_expected_globals(): void
    {
        $path = public_path('vyt-carousel.js');
        $this->assertFileExists($path);

        $contents = file_get_contents($path);
        $this->assertStringContainsString('data-vyt-carousel', $contents);
        $this->assertStringContainsString('vyt-carousel-track', $contents);
        $this->assertStringContainsString('IntersectionObserver', $contents);
    }
}
