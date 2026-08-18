<?php

namespace Tests\Feature\Listings;

use App\Enums\ActivityType;
use App\Enums\PropertyStatus;
use App\Enums\UserRole;
use App\Models\Property;
use App\Models\PropertyAvailabilityWeek;
use App\Models\PropertyView;
use App\Models\Role;
use App\Models\TrackingEvent;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The property hub.
 *
 * These numbers and records already existed, scattered across four screens.
 * The point of this page is that answering "how is this advertisement doing"
 * stops requiring you to know where to look four times.
 */
class PropertyHubTest extends TestCase
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
            'host_id' => User::factory()->create(['name' => 'John Smith'])->id,
            'status'  => PropertyStatus::Active->value,
        ], $attributes));
    }

    public function test_the_header_shows_what_staff_read_down_a_phone_line(): void
    {
        $property = $this->property(['title' => 'Orlando Resort — 2 Bedroom Suite']);

        $response = $this->actingAs($this->staff())
            ->get(route('admin.properties.show', $property))
            ->assertOk();

        $response->assertSee('PROPERTY #'.$property->reference);
        $response->assertSee('Orlando Resort');
        $response->assertSee('John Smith');
        $response->assertSee('ACTIVE');
    }

    public function test_the_counters_reflect_real_records(): void
    {
        $property = $this->property();

        // occurred_at, not viewed_at - the column the table actually has.
        PropertyView::create(['property_id' => $property->id, 'occurred_at' => now()]);
        PropertyView::create(['property_id' => $property->id, 'occurred_at' => now()]);

        TrackingEvent::create([
            'event_type'        => ActivityType::PropertyViewed->value,
            'surface'           => 'web',
            'subject_reference' => $property->reference,
            'occurred_at'       => now(),
        ]);

        PropertyAvailabilityWeek::create([
            'property_id' => $property->id,
            'starts_on'   => now()->addMonth()->toDateString(),
            'ends_on'     => now()->addMonth()->addDays(7)->toDateString(),
        ]);

        $response = $this->actingAs($this->staff())
            ->get(route('admin.properties.show', $property))
            ->assertOk();

        $response->assertSee('Views');
        $response->assertSee('Weeks listed');
        $response->assertSeeInOrder(['2', 'Views']);
    }

    /**
     * Counted from the pivot. A Wishlist is a named list a member owns; the
     * save itself lives in wishlist_properties, and counting the wrong table
     * would report zero saves forever.
     */
    public function test_saves_are_counted_from_the_pivot(): void
    {
        $property = $this->property();
        $wishlistId = DB::table('wishlists')->insertGetId([
            'user_id'    => User::factory()->create()->id,
            'name'       => 'Trips',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('wishlist_properties')->insert([
            'wishlist_id' => $wishlistId,
            'property_id' => $property->id,
            'added_at'    => now(),
        ]);

        $this->actingAs($this->staff())
            ->get(route('admin.properties.show', $property))
            ->assertOk()
            ->assertSeeInOrder(['1', 'Saves']);
    }

    public function test_the_tabs_link_to_this_property_not_to_everything(): void
    {
        $property = $this->property();

        $response = $this->actingAs($this->staff())
            ->get(route('admin.properties.show', $property))
            ->assertOk();

        $response->assertSee(route('admin.properties.edit', $property), false);
        $response->assertSee(route('admin.activity.log', ['subject' => $property->reference]), false);
    }

    /** An inactive listing has no public page, so there is nothing to preview. */
    public function test_preview_is_offered_only_for_an_active_listing(): void
    {
        $draft = $this->property(['status' => PropertyStatus::Draft->value]);

        $this->actingAs($this->staff())
            ->get(route('admin.properties.show', $draft))
            ->assertOk()
            ->assertSee('Preview (inactive)');
    }

    public function test_an_active_listing_offers_the_public_preview(): void
    {
        $property = $this->property();

        $this->actingAs($this->staff())
            ->get(route('admin.properties.show', $property))
            ->assertOk()
            ->assertSee(route('properties.show', $property), false);
    }

    public function test_version_history_is_shown(): void
    {
        $property = $this->property();

        $this->actingAs($this->staff())
            ->get(route('admin.properties.show', $property))
            ->assertOk()
            ->assertSee('Version history');
    }

    // --- access ------------------------------------------------------------------

    public function test_a_host_cannot_open_another_members_hub(): void
    {
        $this->seed(RbacSeeder::class);
        $host = User::factory()->create(['role' => UserRole::Host, 'must_change_password' => false]);
        $host->roles()->sync([Role::where('key', 'host')->firstOrFail()->id]);

        $this->actingAs($host)
            ->get(route('admin.properties.show', $this->property()))
            ->assertForbidden();
    }

    public function test_a_visitor_is_sent_to_login(): void
    {
        $this->get(route('admin.properties.show', $this->property()))
            ->assertRedirect(route('login'));
    }
}
