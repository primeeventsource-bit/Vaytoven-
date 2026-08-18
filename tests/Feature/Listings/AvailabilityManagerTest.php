<?php

namespace Tests\Feature\Listings;

use App\Enums\ActivityType;
use App\Enums\AvailabilityWeekStatus;
use App\Enums\UserRole;
use App\Models\Property;
use App\Models\PropertyAvailabilityWeek;
use App\Models\Role;
use App\Models\TrackingEvent;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityManagerTest extends TestCase
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
        ], $attributes));
    }

    private function week(Property $property, string $starts, string $ends, ?AvailabilityWeekStatus $status = null): PropertyAvailabilityWeek
    {
        return PropertyAvailabilityWeek::create([
            'property_id' => $property->id,
            'starts_on'   => $starts,
            'ends_on'     => $ends,
            'status'      => ($status ?? AvailabilityWeekStatus::Available)->value,
        ]);
    }

    // --- adding ---------------------------------------------------------------

    public function test_a_week_can_be_added(): void
    {
        $property = $this->property();

        $this->actingAs($this->staff())
            ->post(route('admin.properties.availability.store', $property), [
                'starts_on' => now()->addMonth()->toDateString(),
                'ends_on'   => now()->addMonth()->addDays(7)->toDateString(),
            ])
            ->assertRedirect();

        $week = PropertyAvailabilityWeek::sole();

        $this->assertSame($property->id, $week->property_id);
        $this->assertSame(AvailabilityWeekStatus::Available, $week->status);
        $this->assertSame(7, $week->nights());
    }

    public function test_the_end_must_be_after_the_start(): void
    {
        $property = $this->property();

        $this->actingAs($this->staff())
            ->post(route('admin.properties.availability.store', $property), [
                'starts_on' => now()->addMonth()->addDays(7)->toDateString(),
                'ends_on'   => now()->addMonth()->toDateString(),
            ])
            ->assertSessionHasErrors('ends_on');

        $this->assertSame(0, PropertyAvailabilityWeek::count());
    }

    /** Advertising dates that have already gone is a typo, not a choice. */
    public function test_a_week_that_has_already_ended_is_refused(): void
    {
        $property = $this->property();

        $this->actingAs($this->staff())
            ->post(route('admin.properties.availability.store', $property), [
                'starts_on' => now()->subMonths(2)->toDateString(),
                'ends_on'   => now()->subMonth()->toDateString(),
            ])
            ->assertSessionHasErrors('ends_on');

        $this->assertSame(0, PropertyAvailabilityWeek::count());
    }

    /**
     * The unique index catches two identical rows. It does not catch Sep 5-12
     * sitting across Sep 8-15, which would let a traveler make offers on two
     * listings for the same nights and leave the member to find the clash.
     */
    public function test_overlapping_weeks_are_refused(): void
    {
        $property = $this->property();
        $this->week($property, now()->addMonth()->toDateString(), now()->addMonth()->addDays(7)->toDateString());

        $this->actingAs($this->staff())
            ->post(route('admin.properties.availability.store', $property), [
                'starts_on' => now()->addMonth()->addDays(3)->toDateString(),
                'ends_on'   => now()->addMonth()->addDays(10)->toDateString(),
            ])
            ->assertSessionHasErrors('starts_on');

        $this->assertSame(1, PropertyAvailabilityWeek::count());
    }

    /** Back-to-back weeks are not an overlap: one ends the day the next begins. */
    public function test_adjacent_weeks_are_allowed(): void
    {
        $property = $this->property();
        $first = now()->addMonth();

        $this->week($property, $first->toDateString(), $first->copy()->addDays(7)->toDateString());

        $this->actingAs($this->staff())
            ->post(route('admin.properties.availability.store', $property), [
                'starts_on' => $first->copy()->addDays(7)->toDateString(),
                'ends_on'   => $first->copy()->addDays(14)->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, PropertyAvailabilityWeek::count());
    }

    // --- changing --------------------------------------------------------------

    public function test_a_status_can_be_changed(): void
    {
        $property = $this->property();
        $week = $this->week($property, now()->addMonth()->toDateString(), now()->addMonth()->addDays(7)->toDateString());

        $this->actingAs($this->staff())
            ->patch(route('admin.properties.availability.update', [$property, $week]), [
                'status' => AvailabilityWeekStatus::Unavailable->value,
            ])
            ->assertRedirect();

        $this->assertSame(AvailabilityWeekStatus::Unavailable, $week->refresh()->status);
    }

    /**
     * "The member withdrew this" and "staff closed this" are different facts
     * after the event, so who changed it is recorded every time.
     */
    public function test_the_person_who_changed_it_is_recorded(): void
    {
        $property = $this->property();
        $week = $this->week($property, now()->addMonth()->toDateString(), now()->addMonth()->addDays(7)->toDateString());
        $staff = $this->staff();

        $this->actingAs($staff)
            ->patch(route('admin.properties.availability.update', [$property, $week]), [
                'status' => AvailabilityWeekStatus::Closed->value,
            ]);

        $this->assertSame($staff->id, $week->refresh()->updated_by_user_id);
    }

    public function test_a_week_can_be_removed(): void
    {
        $property = $this->property();
        $week = $this->week($property, now()->addMonth()->toDateString(), now()->addMonth()->addDays(7)->toDateString());

        $this->actingAs($this->staff())
            ->delete(route('admin.properties.availability.destroy', [$property, $week]))
            ->assertRedirect();

        $this->assertSame(0, PropertyAvailabilityWeek::count());
    }

    public function test_a_week_cannot_be_changed_through_another_propertys_url(): void
    {
        $mine  = $this->property();
        $other = $this->property();
        $week  = $this->week($other, now()->addMonth()->toDateString(), now()->addMonth()->addDays(7)->toDateString());

        $this->actingAs($this->staff())
            ->patch(route('admin.properties.availability.update', [$mine, $week]), [
                'status' => AvailabilityWeekStatus::Closed->value,
            ])
            ->assertNotFound();
    }

    // --- audit ------------------------------------------------------------------

    public function test_changes_reach_the_activity_log_and_the_audit_log(): void
    {
        $property = $this->property();

        $this->actingAs($this->staff())
            ->post(route('admin.properties.availability.store', $property), [
                'starts_on' => now()->addMonth()->toDateString(),
                'ends_on'   => now()->addMonth()->addDays(7)->toDateString(),
            ]);

        $this->assertDatabaseHas('admin_audit_logs', [
            'action'     => 'property_availability.added',
            'subject_id' => $property->id,
        ]);

        $event = TrackingEvent::where('event_type', ActivityType::AvailabilityChanged->value)->sole();
        $this->assertSame($property->reference, $event->subject_reference);
    }

    // --- access -------------------------------------------------------------------

    /** Members manage their own dates. */
    public function test_a_host_can_manage_their_own_availability(): void
    {
        $this->seed(RbacSeeder::class);
        $host = User::factory()->create(['role' => UserRole::Host, 'must_change_password' => false]);
        $host->roles()->sync([Role::where('key', 'host')->firstOrFail()->id]);

        $property = $this->property(['host_id' => $host->id]);

        $this->actingAs($host)
            ->post(route('admin.properties.availability.store', $property), [
                'starts_on' => now()->addMonth()->toDateString(),
                'ends_on'   => now()->addMonth()->addDays(7)->toDateString(),
            ])
            ->assertRedirect();

        $this->assertSame(1, PropertyAvailabilityWeek::count());
    }

    public function test_a_host_cannot_manage_someone_elses_availability(): void
    {
        $this->seed(RbacSeeder::class);
        $host = User::factory()->create(['role' => UserRole::Host, 'must_change_password' => false]);
        $host->roles()->sync([Role::where('key', 'host')->firstOrFail()->id]);

        $this->actingAs($host)
            ->post(route('admin.properties.availability.store', $this->property()), [
                'starts_on' => now()->addMonth()->toDateString(),
                'ends_on'   => now()->addMonth()->addDays(7)->toDateString(),
            ])
            ->assertForbidden();

        $this->assertSame(0, PropertyAvailabilityWeek::count());
    }

    // --- the builder ----------------------------------------------------------------

    public function test_the_builder_shows_the_manager_not_a_placeholder(): void
    {
        $property = $this->property();
        $this->week($property, now()->addMonth()->toDateString(), now()->addMonth()->addDays(7)->toDateString());

        $response = $this->actingAs($this->staff())
            ->get(route('admin.properties.edit', $property))
            ->assertOk();

        $response->assertSee('Add week');
        $response->assertDontSee('The availability manager is the next piece');
    }

    /** Past weeks stay visible: "what did you advertise last autumn" is a real question. */
    public function test_past_weeks_are_shown_rather_than_hidden(): void
    {
        $property = $this->property();
        $this->week($property, now()->subMonths(3)->toDateString(), now()->subMonths(3)->addDays(7)->toDateString());

        $this->actingAs($this->staff())
            ->get(route('admin.properties.edit', $property))
            ->assertOk()
            ->assertSee('Past');
    }
}
