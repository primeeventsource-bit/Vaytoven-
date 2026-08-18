<?php

namespace Tests\Feature\Listings;

use App\Enums\ActivityType;
use App\Enums\AvailabilityWeekStatus;
use App\Enums\PropertyStatus;
use App\Enums\UserRole;
use App\Models\Property;
use App\Models\PropertyAvailabilityWeek;
use App\Models\PropertyPhoto;
use App\Models\Role;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Support\Listings\ListingReadiness;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Save draft → submit for review → activate, and pause.
 *
 * Status used to be a dropdown, which let anyone set a listing Active with no
 * photos and no dates. A member pays once for a 180-day advertising period and
 * the clock starts when the listing goes live, so activating an empty one
 * spends part of what they bought on an advertisement nobody can act on.
 */
class PublishingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create(['role' => UserRole::SuperAdmin, 'must_change_password' => false]);
        $user->roles()->sync([Role::where('key', 'super_admin')->firstOrFail()->id]);

        return $user;
    }

    private function property(array $attributes = []): Property
    {
        return Property::factory()->create(array_merge([
            'host_id' => User::factory()->create()->id,
            'status'  => PropertyStatus::Draft->value,
        ], $attributes));
    }

    /** A listing with everything it needs to be advertised. */
    private function readyProperty(): Property
    {
        $property = $this->property([
            'title'       => 'Ko Olina Suite',
            'description' => 'A two-bedroom suite a short walk from the lagoon.',
            'city'        => 'Kapolei',
        ]);

        PropertyPhoto::create([
            'property_id' => $property->id,
            'url'         => 'https://images.example.com/a.jpg',
            'sort_order'  => 1,
        ]);

        PropertyAvailabilityWeek::create([
            'property_id' => $property->id,
            'starts_on'   => now()->addMonth()->toDateString(),
            'ends_on'     => now()->addMonth()->addDays(7)->toDateString(),
            'status'      => AvailabilityWeekStatus::Available->value,
        ]);

        return $property->refresh();
    }

    // --- readiness ---------------------------------------------------------------

    public function test_an_empty_listing_reports_every_blocker(): void
    {
        $blockers = ListingReadiness::blockers($this->property([
            'title' => '', 'description' => null, 'short_description' => null, 'city' => null,
        ]));

        $this->assertNotEmpty($blockers);
        $this->assertTrue(collect($blockers)->contains(fn ($b) => str_contains($b, 'photo')));
        $this->assertTrue(collect($blockers)->contains(fn ($b) => str_contains($b, 'week')));
    }

    public function test_a_complete_listing_is_ready(): void
    {
        $this->assertTrue(ListingReadiness::isReady($this->readyProperty()));
    }

    /** Either description satisfies it — insisting on both invites paste. */
    public function test_a_short_description_alone_satisfies_the_description_check(): void
    {
        $property = $this->readyProperty();
        $property->update(['description' => null, 'short_description' => 'Two bedrooms by the lagoon.']);

        $this->assertTrue(ListingReadiness::isReady($property->refresh()));
    }

    /** A week that has passed is not something a traveler can act on. */
    public function test_only_upcoming_available_weeks_count(): void
    {
        $property = $this->readyProperty();
        $property->availabilityWeeks()->update([
            'starts_on' => now()->subMonths(2)->toDateString(),
            'ends_on'   => now()->subMonth()->toDateString(),
        ]);

        $blockers = ListingReadiness::blockers($property->refresh());

        $this->assertTrue(collect($blockers)->contains(fn ($b) => str_contains($b, 'week')));
    }

    public function test_a_withdrawn_week_does_not_count(): void
    {
        $property = $this->readyProperty();
        $property->availabilityWeeks()->update(['status' => AvailabilityWeekStatus::Unavailable->value]);

        $this->assertFalse(ListingReadiness::isReady($property->refresh()));
    }

    // --- transitions --------------------------------------------------------------

    public function test_a_ready_listing_can_be_activated(): void
    {
        $property = $this->readyProperty();

        $this->actingAs($this->staff())
            ->post(route('admin.properties.transition', $property), ['to' => 'active'])
            ->assertRedirect();

        $this->assertSame(PropertyStatus::Active, $property->refresh()->status);
    }

    /** The whole point of the check. */
    public function test_an_unready_listing_cannot_be_activated(): void
    {
        $property = $this->property(['title' => 'Bare listing']);

        $this->actingAs($this->staff())
            ->post(route('admin.properties.transition', $property), ['to' => 'active'])
            ->assertSessionHasErrors('status');

        $this->assertSame(PropertyStatus::Draft, $property->refresh()->status);
    }

    public function test_it_can_be_submitted_for_review_without_being_ready(): void
    {
        // Submitting is asking someone to look, which is exactly what an
        // incomplete listing needs.
        $property = $this->property();

        $this->actingAs($this->staff())
            ->post(route('admin.properties.transition', $property), ['to' => 'pending_review'])
            ->assertRedirect();

        $this->assertSame(PropertyStatus::PendingReview, $property->refresh()->status);
    }

    /** Taking something down never needs permission. */
    public function test_an_active_listing_can_always_be_paused(): void
    {
        $property = $this->readyProperty();
        $property->update(['status' => PropertyStatus::Active->value]);

        $this->actingAs($this->staff())
            ->post(route('admin.properties.transition', $property), ['to' => 'paused'])
            ->assertRedirect();

        $this->assertSame(PropertyStatus::Paused, $property->refresh()->status);
    }

    public function test_an_unknown_status_is_refused(): void
    {
        $property = $this->property();

        $this->actingAs($this->staff())
            ->post(route('admin.properties.transition', $property), ['to' => 'deleted'])
            ->assertSessionHasErrors('to');
    }

    // --- the builder no longer sets status ------------------------------------------

    /**
     * A stale form field must not quietly republish a paused listing on the
     * next save.
     */
    public function test_saving_the_builder_cannot_change_status(): void
    {
        $property = $this->readyProperty();
        $property->update(['status' => PropertyStatus::Paused->value]);

        $this->actingAs($this->staff())
            ->patch(route('admin.properties.update', $property), [
                'title'              => $property->title,
                'host_id'            => $property->host_id,
                'location_precision' => 'approximate',
                'status'             => PropertyStatus::Active->value,
            ]);

        $this->assertSame(PropertyStatus::Paused, $property->refresh()->status);
    }

    public function test_the_builder_shows_the_blockers(): void
    {
        $property = $this->property(['title' => 'Bare listing']);

        $this->actingAs($this->staff())
            ->get(route('admin.properties.edit', $property))
            ->assertOk()
            ->assertSee('Not ready to go live')
            ->assertSee('needs at least one photo', false);
    }

    // --- trail ------------------------------------------------------------------------

    public function test_activation_and_pausing_reach_the_activity_log(): void
    {
        $property = $this->readyProperty();

        $this->actingAs($this->staff())
            ->post(route('admin.properties.transition', $property), ['to' => 'active']);
        $this->actingAs($this->staff())
            ->post(route('admin.properties.transition', $property), ['to' => 'paused']);

        $this->assertSame(1, TrackingEvent::where('event_type', ActivityType::AdvertisementActivated->value)->count());
        $this->assertSame(1, TrackingEvent::where('event_type', ActivityType::AdvertisementPaused->value)->count());

        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'property.status_changed']);
    }

    public function test_a_host_cannot_publish_another_members_listing(): void
    {
        $this->seed(RbacSeeder::class);
        $host = User::factory()->create(['role' => UserRole::Host, 'must_change_password' => false]);
        $host->roles()->sync([Role::where('key', 'host')->firstOrFail()->id]);

        $property = $this->readyProperty();

        $this->actingAs($host)
            ->post(route('admin.properties.transition', $property), ['to' => 'active'])
            ->assertForbidden();

        $this->assertSame(PropertyStatus::Draft, $property->refresh()->status);
    }
}
