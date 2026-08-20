<?php

namespace Tests\Feature\Analytics;

use App\Models\Property;
use App\Models\PropertyView;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Services\Analytics\MemberEngagementMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The member-facing engagement map.
 *
 * A member sees where their advertisement is getting attention — an
 * approximate city and a count. They must not see IP addresses, user agents,
 * visitor ids, or exact coordinates. That detail is evidence, it belongs to
 * the admin side, and the difference between the two maps is the whole point.
 */
class MemberEngagementMapTest extends TestCase
{
    use RefreshDatabase;

    private function click(string $city, string $country, float $lat, float $lng, ?string $ip = null): void
    {
        TrackingEvent::create([
            'event_uuid'  => (string) Str::uuid(),
            'event_type'  => 'cta_click',
            'surface'     => 'web',
            'ip_address'  => $ip ?? '203.0.113.'.random_int(2, 250),
            'city'        => $city,
            'country'     => $country,
            'latitude'    => $lat,
            'longitude'   => $lng,
            'user_agent'  => 'Mozilla/5.0 (very identifying string)',
            'metadata'    => ['cta' => 'submit_offer'],
            'occurred_at' => now()->subDay(),
        ]);
    }

    private function recordView(Property $property, string $city, float $lat, float $lng): void
    {
        PropertyView::create([
            'property_id' => $property->id,
            'occurred_at' => now()->subDay(),
            'city'        => $city,
            'country'     => 'US',
            'latitude'    => $lat,
            'longitude'   => $lng,
            'ip_address'  => '198.51.100.7',
        ]);
    }

    // --- aggregation --------------------------------------------------------

    public function test_it_groups_clicks_by_city_with_counts(): void
    {
        $property = Property::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->click('Orlando', 'US', 28.5383, -81.3792);
        }
        for ($i = 0; $i < 3; $i++) {
            $this->click('Tampa', 'US', 27.9506, -82.4572);
        }

        $map = app(MemberEngagementMap::class)->build(collect([$property]), 30);

        $this->assertSame(8, $map['totals']['ad_views']);

        $cities = collect($map['pins'])->pluck('ad_views', 'city');
        $this->assertSame(5, $cities['Orlando']);
        $this->assertSame(3, $cities['Tampa']);
    }

    public function test_pins_are_ordered_by_engagement(): void
    {
        $property = Property::factory()->create();

        for ($i = 0; $i < 3; $i++) { $this->click('Small Town', 'US', 40.0, -80.0); }
        for ($i = 0; $i < 9; $i++) { $this->click('Big City', 'US', 41.0, -81.0); }

        $map = app(MemberEngagementMap::class)->build(collect([$property]), 30);

        $this->assertSame('Big City', $map['pins'][0]['city']);
    }

    // --- privacy -------------------------------------------------------------

    /**
     * The load-bearing test. Nothing identifying may appear in the payload the
     * member's page is built from.
     */
    public function test_the_payload_contains_nothing_identifying(): void
    {
        $property = Property::factory()->create();

        for ($i = 0; $i < 4; $i++) {
            $this->click('Orlando', 'US', 28.5383, -81.3792, '203.0.113.44');
        }

        $encoded = json_encode(app(MemberEngagementMap::class)->build(collect([$property]), 30));

        $this->assertStringNotContainsString('203.0.113.44', $encoded, 'An IP address reached the member.');
        $this->assertStringNotContainsString('Mozilla', $encoded, 'A user agent reached the member.');
        $this->assertStringNotContainsString('ip_address', $encoded);
        $this->assertStringNotContainsString('user_agent', $encoded);
        $this->assertStringNotContainsString('visitor_id', $encoded);
    }

    /** Coordinates are rounded to a metro, not a street. */
    public function test_coordinates_are_blurred(): void
    {
        $property = Property::factory()->create();

        for ($i = 0; $i < 4; $i++) {
            $this->click('Orlando', 'US', 28.538312, -81.379234);
        }

        $pin = app(MemberEngagementMap::class)->build(collect([$property]), 30)['pins'][0];

        // One decimal ≈ 11km. The exact figure must not survive.
        $this->assertSame(28.5, $pin['lat']);
        $this->assertSame(-81.4, $pin['lng']);
    }

    /**
     * A single click from a small town identifies a person once the member
     * combines it with anything else they know. Aggregation only anonymises
     * when there is something to aggregate.
     */
    public function test_a_city_below_the_threshold_is_not_pinned(): void
    {
        $property = Property::factory()->create();

        $this->click('Tiny Village', 'US', 44.1, -72.1);          // 1 — hidden
        for ($i = 0; $i < 4; $i++) {
            $this->click('Orlando', 'US', 28.5, -81.4);           // 4 — shown
        }

        $map = app(MemberEngagementMap::class)->build(collect([$property]), 30);
        $cities = collect($map['pins'])->pluck('city');

        $this->assertContains('Orlando', $cities);
        $this->assertNotContains('Tiny Village', $cities);

        // The click still counts toward the total — it is real engagement,
        // just not something to put a marker on.
        $this->assertSame(5, $map['totals']['ad_views']);
    }

    /** Unresolved locations are dropped, not pinned at 0,0. */
    public function test_events_without_a_city_are_not_pinned(): void
    {
        $property = Property::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            TrackingEvent::create([
                'event_uuid' => (string) Str::uuid(), 'event_type' => 'cta_click',
                'surface' => 'web', 'metadata' => [], 'occurred_at' => now()->subDay(),
            ]);
        }

        $map = app(MemberEngagementMap::class)->build(collect([$property]), 30);

        $this->assertSame(5, $map['totals']['ad_views']);
        $this->assertSame([], $map['pins']);
    }

    // --- windows and filtering ------------------------------------------------

    public function test_the_window_excludes_older_activity(): void
    {
        $property = Property::factory()->create();

        for ($i = 0; $i < 4; $i++) { $this->click('Orlando', 'US', 28.5, -81.4); }

        TrackingEvent::create([
            'event_uuid' => (string) Str::uuid(), 'event_type' => 'cta_click',
            'surface' => 'web', 'city' => 'Orlando', 'country' => 'US',
            'latitude' => 28.5, 'longitude' => -81.4, 'metadata' => [],
            'occurred_at' => now()->subDays(60),
        ]);

        $this->assertSame(4, app(MemberEngagementMap::class)->build(collect([$property]), 7)['totals']['ad_views']);
        $this->assertSame(5, app(MemberEngagementMap::class)->build(collect([$property]), 0)['totals']['ad_views']);
    }

    /** Views and interactions are one figure now, so a view alone is an ad view. */
    public function test_views_count_towards_ad_views(): void
    {
        $property = Property::factory()->create();

        for ($i = 0; $i < 6; $i++) { $this->recordView($property, 'Miami', 25.76, -80.19); }

        $map = app(MemberEngagementMap::class)->build(collect([$property]), 30);

        $this->assertSame(6, $map['totals']['ad_views']);
        $this->assertSame('Miami', $map['pins'][0]['city']);
    }

    public function test_a_member_with_no_listings_gets_an_empty_map(): void
    {
        $map = app(MemberEngagementMap::class)->build(collect(), 30);

        $this->assertSame(0, $map['totals']['ad_views']);
        $this->assertSame([], $map['pins']);
    }

    public function test_it_can_be_filtered_to_one_property(): void
    {
        $owner = User::factory()->create();
        $a = Property::factory()->create(['host_id' => $owner->id]);
        $b = Property::factory()->create(['host_id' => $owner->id]);

        for ($i = 0; $i < 4; $i++) { $this->recordView($a, 'Austin', 30.2, -97.7); }
        for ($i = 0; $i < 7; $i++) { $this->recordView($b, 'Denver', 39.7, -104.9); }

        $map = app(MemberEngagementMap::class)->build(collect([$a, $b]), 30, $a->id);

        $this->assertSame(4, $map['totals']['ad_views']);
        $this->assertSame('Austin', $map['pins'][0]['city']);
    }
}
