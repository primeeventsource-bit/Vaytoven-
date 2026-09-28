<?php

namespace Tests\Feature\Landing;

use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Models\PropertyPhoto;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The homepage features real advertisements.
 *
 * It used to show four invented listings — Big Sur, Puglia, the Catskills,
 * Marrakech — with invented ratings and dates, linking nowhere. A homepage
 * that advertises inventory the company does not have is the kind of thing a
 * visitor finds out by clicking.
 */
class HomepageFeaturedListingsTest extends TestCase
{
    use RefreshDatabase;

    private function listing(string $title, ?string $publishedAt, string $status = 'active'): Property
    {
        $property = Property::factory()->create(['title' => $title, 'status' => $status, 'city' => 'Miami', 'region' => 'FL']);
        PropertyPhoto::create(['property_id' => $property->id, 'url' => 'https://images.example.com/'.$property->id.'.jpg', 'sort_order' => 1]);
        $property->forceFill(['published_at' => $publishedAt])->saveQuietly();

        return $property->refresh();
    }

    /** The featured row only — the rest of the page is not under test here. */
    private function featuredGrid(): string
    {
        $body = $this->get('/')->assertOk()->getContent();
        $from = strpos($body, '<div class="featured-grid">');

        return $from === false ? '' : substr($body, $from, strpos($body, '</section>', $from) - $from);
    }

    public function test_it_shows_the_newest_live_listings_and_links_to_them(): void
    {
        $newest = $this->listing('Newest advertisement', '2026-09-20 10:00:00');
        $older  = $this->listing('Older advertisement', '2026-01-02 10:00:00');
        $fifth  = $this->listing('Fifth advertisement', '2025-01-01 10:00:00');
        $this->listing('Second advertisement', '2026-09-01 10:00:00');
        $this->listing('Third advertisement', '2026-08-01 10:00:00');

        $response = $this->get('/')->assertOk();

        $response->assertSeeInOrder(['Just listed on', 'Newest advertisement', 'Second advertisement', 'Third advertisement', 'Older advertisement']);
        $response->assertSee(route('properties.show', $newest), false);
        $response->assertSee('Miami, FL');
        $response->assertSee('Sleeps '.$newest->capacity);

        // Only four are featured; the fifth waits its turn.
        $response->assertDontSee('Fifth advertisement');
        $response->assertSee(route('properties.show', $older), false);
        $this->assertStringNotContainsString(route('properties.show', $fifth), $response->getContent());

        // And the invented cards are gone from the row for good.
        $grid = $this->featuredGrid();
        foreach (['Cliffside cottage, Big Sur', 'Olive grove villa, Puglia', 'Cedar A-frame, Catskills', 'Riad with rooftop, Marrakech'] as $invented) {
            $this->assertStringNotContainsString($invented, $grid);
        }
    }

    public function test_drafts_paused_listings_and_listings_without_photos_are_not_featured(): void
    {
        $this->listing('Live and photographed', '2026-09-20 10:00:00');
        $this->listing('Paused listing', '2026-09-21 10:00:00', PropertyStatus::Paused->value);
        $this->listing('Draft listing', null, PropertyStatus::Draft->value);

        $noPhoto = Property::factory()->create(['title' => 'No photo yet', 'status' => PropertyStatus::Active->value]);
        $noPhoto->forceFill(['published_at' => '2026-09-22 10:00:00'])->saveQuietly();

        $this->get('/')->assertOk()
            ->assertSee('Live and photographed')
            ->assertDontSee('Paused listing')
            ->assertDontSee('Draft listing')
            ->assertDontSee('No photo yet');
    }

    /**
     * No stars on the cards.
     *
     * The old ones wore invented ratings. A real one cannot exist today:
     * reviews hang off bookings — a product this company retired — so
     * reviews.booking_id is NOT NULL, the table is empty, and nothing can
     * write to it. Better nothing than decoration.
     */
    public function test_no_rating_is_invented_on_the_cards(): void
    {
        $this->listing('Unrated advertisement', '2026-09-20 10:00:00');

        $this->assertStringNotContainsString('★', $this->featuredGrid(), 'A star appeared with no review behind it.');
    }

    /** No listings, no empty shell. */
    public function test_the_section_disappears_when_there_is_nothing_live(): void
    {
        $this->get('/')->assertOk()->assertDontSee('Just listed on');
    }

    public function test_the_featured_cards_carry_no_prices(): void
    {
        $this->listing('Priced advertisement', '2026-09-20 10:00:00')->update(['price_cents' => 31000]);

        $body = $this->get('/')->assertOk()->getContent();
        $grid = substr($body, strpos($body, 'featured-grid'));
        $grid = substr($grid, 0, strpos($grid, '</section>'));

        $this->assertDoesNotMatchRegularExpression('/\$\s?[0-9]/', $grid, 'A price is back on the featured cards.');
    }
    /**
     * The card points at the photo stream, not the url column.
     *
     * Uploaded photos live in a private bucket: the row carries a disk and a
     * path, and url stays null. The homepage read url directly, so every real
     * listing rendered <img src=""> — a row of broken pictures with the alt
     * text showing. Stays already used displayUrl(); now both do.
     */
    public function test_uploaded_photos_are_served_through_the_photo_route(): void
    {
        $property = Property::factory()->create(['title' => 'Uploaded advertisement', 'status' => 'active']);
        $property->forceFill(['published_at' => '2026-09-20 10:00:00'])->saveQuietly();

        $photo = PropertyPhoto::create([
            'property_id' => $property->id,
            'disk'        => 's3',
            'path'        => 'properties/'.$property->id.'/cover.jpg',
            'url'         => null,
            'sort_order'  => 1,
        ]);

        $grid = $this->featuredGrid();

        $this->assertStringContainsString('src="'.route('properties.photo', $photo).'"', $grid);
        $this->assertStringNotContainsString('src=""', $grid, 'A featured card has an empty image source.');
    }

    /** The cover a member chose is the one the homepage shows. */
    public function test_the_chosen_cover_leads_the_card(): void
    {
        $property = Property::factory()->create(['title' => 'Covered advertisement', 'status' => 'active']);
        $property->forceFill(['published_at' => '2026-09-20 10:00:00'])->saveQuietly();

        PropertyPhoto::create(['property_id' => $property->id, 'url' => 'https://images.example.com/first.jpg', 'sort_order' => 1]);
        PropertyPhoto::create(['property_id' => $property->id, 'url' => 'https://images.example.com/cover.jpg', 'sort_order' => 9, 'is_cover' => true]);

        $this->assertStringContainsString('https://images.example.com/cover.jpg', $this->featuredGrid());
    }
}