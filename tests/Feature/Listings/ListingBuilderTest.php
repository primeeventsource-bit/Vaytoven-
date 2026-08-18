<?php

namespace Tests\Feature\Listings;

use App\Enums\PropertyStatus;
use App\Enums\UserRole;
use App\Models\Amenity;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'must_change_password' => false,
        ]);
        $user->roles()->sync([Role::where('key', 'super_admin')->firstOrFail()->id]);

        return $user;
    }

    private function property(array $attributes = []): Property
    {
        return Property::factory()->create(array_merge([
            'host_id' => User::factory()->create()->id,
        ], $attributes));
    }

    /** @return array<string, mixed> a complete, valid submission */
    private function payload(Property $property, array $overrides = []): array
    {
        return array_merge([
            'title'              => $property->title,
            'host_id'            => $property->host_id,
            'status'             => $property->status->value,
            'location_precision' => 'approximate',
        ], $overrides);
    }

    public function test_the_builder_renders_every_section(): void
    {
        $response = $this->actingAs($this->staff())
            ->get(route('admin.properties.edit', $this->property()))
            ->assertOk();

        foreach (['Property basics', 'Location', 'Property details', 'Description',
                  'Amenities', 'Offer settings', 'Availability'] as $section) {
            $response->assertSee($section);
        }
    }

    public function test_the_reference_is_shown_so_staff_can_quote_it(): void
    {
        $property = $this->property();

        $this->actingAs($this->staff())
            ->get(route('admin.properties.edit', $property))
            ->assertOk()
            ->assertSee($property->reference);
    }

    public function test_it_saves_the_builder_fields(): void
    {
        $property = $this->property();

        $this->actingAs($this->staff())
            ->patch(route('admin.properties.update', $property), $this->payload($property, [
                'headline'          => 'Spacious Two-Bedroom Resort Stay',
                'short_description' => 'Two bedrooms, resort pool, full kitchen.',
                'property_kind'     => 'villa',
                'view_type'         => 'ocean',
                'square_feet'       => 1250,
                'bed_configuration' => '1 king, 2 queens',
            ]))
            ->assertRedirect(route('admin.properties.edit', $property));

        $property->refresh();

        $this->assertSame('Spacious Two-Bedroom Resort Stay', $property->headline);
        $this->assertSame('villa', $property->property_kind);
        $this->assertSame('ocean', $property->view_type);
        $this->assertSame(1250, $property->square_feet);
        $this->assertSame('1 king, 2 queens', $property->bed_configuration);
    }

    /** Blank rows are how a repeating field says "nothing here". */
    public function test_empty_highlights_are_discarded(): void
    {
        $property = $this->property();

        $this->actingAs($this->staff())
            ->patch(route('admin.properties.update', $property), $this->payload($property, [
                'highlights' => ['Sleeps up to 6', '', '  ', 'Resort pool', ''],
            ]));

        $this->assertSame(['Sleeps up to 6', 'Resort pool'], $property->refresh()->highlights);
    }

    // --- money ----------------------------------------------------------------

    /**
     * 249.99 * 100 is 24998.999... in binary floating point, which truncates
     * to 24998 and quietly undercharges by a cent.
     */
    public function test_the_minimum_offer_is_stored_as_rounded_cents(): void
    {
        $property = $this->property();

        $this->actingAs($this->staff())
            ->patch(route('admin.properties.update', $property), $this->payload($property, [
                'minimum_offer_dollars' => '249.99',
            ]));

        $this->assertSame(24999, $property->refresh()->minimum_offer_cents);
    }

    public function test_clearing_the_minimum_offer_stores_null_not_zero(): void
    {
        $property = $this->property(['minimum_offer_cents' => 50000]);

        $this->actingAs($this->staff())
            ->patch(route('admin.properties.update', $property),
                $this->payload($property, ['minimum_offer_dollars' => '']));

        $this->assertNull($property->refresh()->minimum_offer_cents);
    }

    // --- switches --------------------------------------------------------------

    /**
     * An unchecked box is absent from a form post, not false. Without handling
     * that, a setting can be turned on and never off — the switch appears to
     * work in one direction only.
     */
    public function test_a_setting_can_be_turned_off(): void
    {
        $property = $this->property(['allow_offers' => true]);

        $this->actingAs($this->staff())
            ->patch(route('admin.properties.update', $property), $this->payload($property));

        $this->assertFalse($property->refresh()->allow_offers);
    }

    public function test_a_setting_can_be_turned_on(): void
    {
        $property = $this->property(['allow_offers' => false]);

        $this->actingAs($this->staff())
            ->patch(route('admin.properties.update', $property),
                $this->payload($property, ['allow_offers' => '1']));

        $this->assertTrue($property->refresh()->allow_offers);
    }

    // --- amenities --------------------------------------------------------------

    public function test_amenities_are_synced_not_appended(): void
    {
        $property = $this->property();
        $wifi = Amenity::create(['slug' => 'wifi', 'label' => 'Wi-Fi', 'category' => 'indoor']);
        $pool = Amenity::create(['slug' => 'pool', 'label' => 'Pool', 'category' => 'outdoor']);
        $property->amenities()->sync([$wifi->id]);

        $this->actingAs($this->staff())
            ->patch(route('admin.properties.update', $property),
                $this->payload($property, ['amenities' => [$pool->id]]));

        $this->assertSame([$pool->id], $property->refresh()->amenities->pluck('id')->all());
    }

    /**
     * firstOrCreate, not create: two staff adding "Pickleball" on different
     * listings must land on one amenity, or the filter ends up with duplicates
     * that each match half the listings.
     */
    public function test_a_custom_amenity_is_shared_rather_than_duplicated(): void
    {
        $first  = $this->property();
        $second = $this->property();

        foreach ([$first, $second] as $property) {
            $this->actingAs($this->staff())
                ->patch(route('admin.properties.update', $property),
                    $this->payload($property, ['custom_amenity' => 'Pickleball court']));
        }

        $this->assertSame(1, Amenity::where('slug', 'pickleball-court')->count());
        $this->assertTrue($first->refresh()->amenities->contains('slug', 'pickleball-court'));
        $this->assertTrue($second->refresh()->amenities->contains('slug', 'pickleball-court'));
    }

    // --- audit and access --------------------------------------------------------

    /**
     * "Staff edited this listing" is not evidence. During a dispute the
     * question is which fields changed.
     */
    public function test_the_audit_entry_names_the_fields_that_changed(): void
    {
        $property = $this->property(['headline' => 'Before']);

        $this->actingAs($this->staff())
            ->patch(route('admin.properties.update', $property),
                $this->payload($property, ['headline' => 'After']));

        $log = \App\Models\AdminAuditLog::where('action', 'property.updated')->sole();

        $this->assertContains('headline', $log->payload['changed']);
        $this->assertSame($property->reference, $log->payload['reference']);
    }

    /**
     * The permission alone is not the gate.
     *
     * The RBAC `host` role grants properties.edit so hosts can maintain their
     * OWN listings. Route middleware alone would therefore have let any host
     * open any member's property in the builder. This is the assertion that
     * caught it.
     */
    public function test_a_host_cannot_open_someone_elses_listing(): void
    {
        $this->seed(RbacSeeder::class);

        $host = User::factory()->create(['role' => UserRole::Host, 'must_change_password' => false]);
        $host->roles()->sync([Role::where('key', 'host')->firstOrFail()->id]);

        $this->assertTrue($host->hasPermission('properties.edit'), 'a host does hold this permission');

        $this->actingAs($host)
            ->get(route('admin.properties.edit', $this->property()))
            ->assertForbidden();
    }

    public function test_a_host_cannot_save_someone_elses_listing(): void
    {
        $this->seed(RbacSeeder::class);

        $host = User::factory()->create(['role' => UserRole::Host, 'must_change_password' => false]);
        $host->roles()->sync([Role::where('key', 'host')->firstOrFail()->id]);

        $property = $this->property();

        $this->actingAs($host)
            ->patch(route('admin.properties.update', $property),
                $this->payload($property, ['headline' => 'Hijacked']))
            ->assertForbidden();

        $this->assertNotSame('Hijacked', $property->refresh()->headline);
    }

    /** Their own listing is theirs to maintain. */
    public function test_a_host_can_build_their_own_listing(): void
    {
        $this->seed(RbacSeeder::class);

        $host = User::factory()->create(['role' => UserRole::Host, 'must_change_password' => false]);
        $host->roles()->sync([Role::where('key', 'host')->firstOrFail()->id]);

        $property = $this->property(['host_id' => $host->id]);

        $this->actingAs($host)
            ->get(route('admin.properties.edit', $property))
            ->assertOk();
    }

    public function test_a_visitor_cannot_save_a_listing(): void
    {
        $property = $this->property();

        $this->patch(route('admin.properties.update', $property), $this->payload($property))
            ->assertRedirect(route('login'));
    }

    public function test_an_invalid_status_is_refused(): void
    {
        $property = $this->property();

        $this->actingAs($this->staff())
            ->patch(route('admin.properties.update', $property),
                $this->payload($property, ['status' => 'nonsense']))
            ->assertSessionHasErrors('status');
    }

    public function test_the_precision_setting_is_required(): void
    {
        $property = $this->property();

        $payload = $this->payload($property);
        unset($payload['location_precision']);

        $this->actingAs($this->staff())
            ->patch(route('admin.properties.update', $property), $payload)
            ->assertSessionHasErrors('location_precision');
    }
}
