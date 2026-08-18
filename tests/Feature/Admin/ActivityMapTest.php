<?php

namespace Tests\Feature\Admin;

use App\Enums\ActivityType;
use App\Enums\UserRole;
use App\Models\Role;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Services\Activity\ActivityMap;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityMapTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create(['role' => UserRole::SuperAdmin, 'must_change_password' => false]);
        $user->roles()->sync([Role::where('key', 'super_admin')->firstOrFail()->id]);

        return $user;
    }

    private function event(array $attributes = []): TrackingEvent
    {
        return TrackingEvent::create(array_merge([
            'event_type'  => ActivityType::PropertyViewed->value,
            'surface'     => 'web',
            'ip_address'  => '73.12.44.184',
            'city'        => 'Miami',
            'region'      => 'FL',
            'country'     => 'US',
            'latitude'    => 25.7617,
            'longitude'   => -80.1918,
            'occurred_at' => now(),
        ], $attributes));
    }

    public function test_events_in_one_city_become_one_pin(): void
    {
        $this->event();
        $this->event();
        $this->event(['event_type' => ActivityType::OfferSubmitted->value]);

        $pins = app(ActivityMap::class)->pins();

        $this->assertCount(1, $pins);
        $this->assertSame('Miami, FL, US', $pins[0]['label']);
        $this->assertSame(3, $pins[0]['total']);
    }

    /**
     * Two lookups of the same city can differ in the sixth decimal place.
     * Grouping by coordinate would scatter one place across pins sitting on
     * top of each other.
     */
    public function test_slightly_different_coordinates_in_one_city_do_not_split(): void
    {
        $this->event(['latitude' => 25.761701, 'longitude' => -80.191801]);
        $this->event(['latitude' => 25.761812, 'longitude' => -80.191912]);

        $this->assertCount(1, app(ActivityMap::class)->pins());
    }

    public function test_a_pin_breaks_down_by_activity(): void
    {
        $this->event();
        $this->event();
        $this->event(['event_type' => ActivityType::OfferSubmitted->value]);

        $breakdown = app(ActivityMap::class)->pins()[0]['breakdown'];

        $this->assertSame(2, $breakdown['Property advertisement viewed']);
        $this->assertSame(1, $breakdown['Offer submitted']);
    }

    public function test_separate_cities_are_separate_pins(): void
    {
        $this->event(['city' => 'Miami', 'latitude' => 25.76, 'longitude' => -80.19]);
        $this->event(['city' => 'Orlando', 'latitude' => 28.53, 'longitude' => -81.37]);

        $this->assertCount(2, app(ActivityMap::class)->pins());
    }

    /** Nothing to plot is not the same as nothing recorded. */
    public function test_events_without_coordinates_are_not_plotted(): void
    {
        $this->event(['latitude' => null, 'longitude' => null]);

        $this->assertCount(0, app(ActivityMap::class)->pins());
    }

    public function test_filtering_by_layer(): void
    {
        $this->event();
        $this->event(['event_type' => ActivityType::OfferSubmitted->value]);

        $offers = app(ActivityMap::class)->pins(['layer' => 'offers']);

        $this->assertSame(1, $offers[0]['total']);
        $this->assertArrayHasKey('Offer submitted', $offers[0]['breakdown']);
        $this->assertArrayNotHasKey('Property advertisement viewed', $offers[0]['breakdown']);
    }

    /** An unknown layer must plot nothing, not everything. */
    public function test_an_unknown_layer_plots_nothing(): void
    {
        $this->event();

        $this->assertCount(0, app(ActivityMap::class)->pins(['layer' => 'nonsense']));
    }

    public function test_filtering_by_date_range(): void
    {
        $this->event(['occurred_at' => now()->subMonths(3)]);
        $this->event(['occurred_at' => now()]);

        $pins = app(ActivityMap::class)->pins(['from' => now()->subWeek()->toDateString()]);

        $this->assertSame(1, $pins[0]['total']);
    }

    // --- the screen ------------------------------------------------------------

    public function test_the_map_renders_and_labels_the_data_approximate(): void
    {
        $this->event();

        $response = $this->actingAs($this->staff())
            ->get(route('admin.activity.map'))
            ->assertOk();

        $response->assertSee('Miami');
        $response->assertSee('approximate GeoIP');
        $response->assertSee('List view');
    }

    /** An empty map should explain itself rather than look broken. */
    public function test_an_empty_map_explains_why(): void
    {
        $this->actingAs($this->staff())
            ->get(route('admin.activity.map'))
            ->assertOk()
            ->assertSee('No located activity');
    }

    public function test_a_member_cannot_read_the_activity_map(): void
    {
        $this->seed(RbacSeeder::class);
        $member = User::factory()->create(['role' => UserRole::Member, 'must_change_password' => false]);

        $this->actingAs($member)->get(route('admin.activity.map'))->assertForbidden();
    }
}
