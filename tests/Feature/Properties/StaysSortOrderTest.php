<?php

namespace Tests\Feature\Properties;

use App\Enums\ActivityType;
use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Models\PropertyView;
use App\Models\TrackingEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stays leads with the newest advertisement.
 *
 * "Newest" is when the listing was published, not when the row was created,
 * last edited, or when the member joined — and a later edit must not push an
 * old listing back to the top.
 */
class StaysSortOrderTest extends TestCase
{
    use RefreshDatabase;

    private function published(string $title, string $publishedAt, string $created = '2020-01-01 00:00:00'): Property
    {
        $property = Property::factory()->create([
            'title' => $title, 'status' => PropertyStatus::Active->value, 'created_at' => $created,
        ]);

        // published_at is stamped by the model; move it to the date under test.
        $property->forceFill(['published_at' => $publishedAt])->saveQuietly();

        return $property->refresh();
    }

    /** @return list<string> */
    private function titlesOn(string $url): array
    {
        return $this->get($url)->assertOk()->viewData('properties')->pluck('title')->all();
    }

    public function test_going_live_stamps_the_publication_date_once_and_editing_never_moves_it(): void
    {
        $this->travelTo('2026-09-01 10:00:00');
        $property = Property::factory()->create(['status' => PropertyStatus::Draft->value]);
        $this->assertNull($property->published_at, 'A draft was treated as published.');

        $this->travelTo('2026-09-10 09:00:00');
        $property->update(['status' => PropertyStatus::Active->value]);
        $this->assertSame('2026-09-10 09:00:00', $property->refresh()->published_at->format('Y-m-d H:i:s'));

        // An edit, a pause and a re-activation all leave it alone.
        $this->travelTo('2026-09-20 12:00:00');
        $property->update(['title' => 'Edited title']);
        $property->update(['status' => PropertyStatus::Paused->value]);
        $property->update(['status' => PropertyStatus::Active->value]);

        $this->assertSame('2026-09-10 09:00:00', $property->refresh()->published_at->format('Y-m-d H:i:s'));
    }

    public function test_newest_first_is_the_default_and_oldest_first_reverses_it(): void
    {
        // Created in a deliberately unhelpful order: the oldest listing has
        // the newest row, and the newest listing the oldest row.
        $this->published('Middle', '2026-06-15 12:00:00', '2026-01-01 00:00:00');
        $this->published('Oldest', '2026-01-05 12:00:00', '2026-09-01 00:00:00');
        $this->published('Newest', '2026-09-20 12:00:00', '2025-01-01 00:00:00');

        $this->assertSame(['Newest', 'Middle', 'Oldest'], $this->titlesOn(route('properties.index')));
        $this->assertSame(['Newest', 'Middle', 'Oldest'], $this->titlesOn(route('properties.index', ['sort' => 'newest'])));
        $this->assertSame(['Oldest', 'Middle', 'Newest'], $this->titlesOn(route('properties.index', ['sort' => 'oldest'])));

        // An unknown sort falls back to the default rather than to no order.
        $this->assertSame(['Newest', 'Middle', 'Oldest'], $this->titlesOn(route('properties.index', ['sort' => 'cheapest'])));
    }

    public function test_only_live_listings_appear(): void
    {
        $this->published('Live listing', '2026-09-01 12:00:00');

        foreach ([PropertyStatus::Draft, PropertyStatus::PendingReview, PropertyStatus::Paused] as $status) {
            Property::factory()->create(['title' => 'Hidden '.$status->value, 'status' => $status->value]);
        }

        $this->assertSame(['Live listing'], $this->titlesOn(route('properties.index')));
    }

    public function test_most_viewed_and_most_clicked_rank_by_their_own_counts(): void
    {
        $this->published('Quiet', '2026-09-20 12:00:00');
        $viewed  = $this->published('Viewed', '2026-02-01 12:00:00');
        $clicked = $this->published('Clicked', '2026-03-01 12:00:00');

        foreach (range(1, 3) as $i) {
            PropertyView::create(['property_id' => $viewed->id, 'visitor_id' => "v{$i}", 'occurred_at' => now()]);
        }
        PropertyView::create(['property_id' => $clicked->id, 'visitor_id' => 'v9', 'occurred_at' => now()]);

        foreach (range(1, 4) as $i) {
            TrackingEvent::create([
                'event_type' => ActivityType::AdvertisementClicked->value, 'surface' => 'web',
                'subject_type' => 'property', 'subject_reference' => $clicked->reference, 'metadata' => [],
            ]);
        }
        TrackingEvent::create([
            'event_type' => ActivityType::AdvertisementClicked->value, 'surface' => 'web',
            'subject_type' => 'property', 'subject_reference' => $viewed->reference, 'metadata' => [],
        ]);

        $this->assertSame(['Viewed', 'Clicked', 'Quiet'], $this->titlesOn(route('properties.index', ['sort' => 'most_viewed'])));
        $this->assertSame(['Clicked', 'Viewed', 'Quiet'], $this->titlesOn(route('properties.index', ['sort' => 'most_clicked'])));
    }

    public function test_the_sort_control_keeps_the_visitor_filters(): void
    {
        $miami = $this->published('Miami condo', '2026-09-01 12:00:00');
        $miami->update(['city' => 'Miami']);

        $body = $this->get(route('properties.index', ['sort' => 'oldest', 'city' => 'Miami']))->assertOk()->getContent();

        $this->assertStringContainsString('name="sort"', $body);
        foreach (['Newest first', 'Oldest first', 'Most viewed', 'Most clicked'] as $label) {
            $this->assertStringContainsString($label, $body);
        }
        $this->assertStringContainsString('<option value="oldest" selected>', $body);
        $this->assertStringContainsString('<input type="hidden" name="city" value="Miami">', $body);
    }

    /** Listings with no publication date on file sort last, not first. */
    public function test_a_listing_without_a_publication_date_sorts_last(): void
    {
        $this->published('Dated', '2026-01-01 12:00:00');
        $undated = Property::factory()->create(['title' => 'Undated', 'status' => PropertyStatus::Active->value]);
        $undated->forceFill(['published_at' => null])->saveQuietly();

        $this->assertSame(['Dated', 'Undated'], $this->titlesOn(route('properties.index')));
        $this->assertSame(['Dated', 'Undated'], $this->titlesOn(route('properties.index', ['sort' => 'oldest'])));
    }
}
