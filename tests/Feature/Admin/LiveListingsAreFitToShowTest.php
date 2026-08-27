<?php

namespace Tests\Feature\Admin;

use App\Enums\AvailabilityWeekStatus;
use App\Enums\PropertyStatus;
use App\Enums\UserRole;
use App\Models\Property;
use App\Models\PropertyPhoto;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An advertisement must not be public before it has something to show.
 *
 * Six paying members' listings ran live with no photographs at all. Not a
 * failed upload — nothing was ever attached, and nothing anywhere said so. The
 * cause was that creating a listing accepted "active" straight from the form,
 * and photos cannot exist at that moment: they attach to a listing by id, and
 * the id does not exist until the listing is saved. So "create as active"
 * published an empty advertisement every single time it was used.
 *
 * The activation endpoint had always checked for photos. Creation went around
 * it. Two things are held here: creation cannot publish, and anything already
 * live that is not fit to show is visible in the admin instead of waiting to be
 * noticed by the member paying for it.
 */
class LiveListingsAreFitToShowTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create([
            'role'                 => UserRole::SuperAdmin,
            'must_change_password' => false,
        ]);

        $user->roles()->sync([Role::where('key', 'super_admin')->firstOrFail()->id]);

        return $user;
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'owner_mode'      => 'new',
            'owner_email'     => 'new.owner@example.org',
            'owner_first_name'=> 'New',
            'owner_last_name' => 'Owner',
            'title'           => 'Marriott Aruba Surf Club',
            'description'     => 'A week on the beach.',
            'city'            => 'Palm Beach',
            'country'         => 'AW',
            'capacity'        => 6,
            'bedrooms'        => 2,
            'beds'            => 3,
            'bathrooms'       => 2,
            'price_dollars'   => '2400.00',
            'listing_type'    => 'rent',
            'status'          => 'draft',
            'listing_source'  => 'host',
            'notify_owner'    => 0,
        ], $overrides);
    }

    // --- creation cannot publish --------------------------------------------------------

    /**
     * The exact move that produced all six. A listing has no photos at the
     * moment it is created, so this can only ever publish an empty page.
     */
    public function test_a_listing_cannot_be_created_active(): void
    {
        $this->actingAs($this->staff())
            ->post(route('admin.properties.store'), $this->payload(['status' => 'active']))
            ->assertSessionHasErrors('status');

        $this->assertSame(0, Property::where('status', PropertyStatus::Active->value)->count());
    }

    public function test_the_refusal_explains_what_to_do_instead(): void
    {
        $this->actingAs($this->staff())
            ->post(route('admin.properties.store'), $this->payload(['status' => 'active']));

        $this->assertStringContainsString(
            'cannot be created as active',
            session('errors')->first('status'),
        );
    }

    public function test_creating_a_draft_still_works(): void
    {
        $this->actingAs($this->staff())
            ->post(route('admin.properties.store'), $this->payload(['status' => 'draft']))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Property::where('status', PropertyStatus::Draft->value)->count());
    }

    public function test_the_create_form_does_not_offer_active(): void
    {
        $response = $this->actingAs($this->staff())->get(route('admin.properties.create'));

        $response->assertOk();
        $response->assertDontSee('<option value="active"', false);
    }

    // --- what is already live ------------------------------------------------------------

    private function liveListing(array $overrides = []): Property
    {
        return Property::factory()->create(array_merge([
            'status'      => PropertyStatus::Active->value,
            'title'       => 'Westgate Smoky Mountain Resort',
            'description' => 'A week in the mountains.',
            'city'        => 'Gatlinburg',
        ], $overrides));
    }

    private function giveItAWeek(Property $property): void
    {
        $property->availabilityWeeks()->create([
            'starts_on' => now()->addWeek()->toDateString(),
            'ends_on'   => now()->addWeeks(2)->toDateString(),
            'status'    => AvailabilityWeekStatus::Available->value,
        ]);
    }

    private function giveItAPhoto(Property $property): void
    {
        PropertyPhoto::create([
            'property_id' => $property->id,
            'disk'        => 'local',
            'path'        => 'p/'.$property->id.'.webp',
            'category'    => 'other',
            'mime_type'   => 'image/webp',
        ]);
    }

    /** The six, in miniature: live, paid for, and nothing on the page. */
    public function test_a_live_listing_with_no_photos_is_flagged_on_the_index(): void
    {
        $property = $this->liveListing();
        $this->giveItAWeek($property);

        $this->actingAs($this->staff())
            ->get(route('admin.properties.index'))
            ->assertOk()
            ->assertSee('missing something a visitor needs')
            ->assertSee('Westgate Smoky Mountain Resort')
            ->assertSee('It needs at least one photo. A listing without one is skipped.');
    }

    public function test_a_complete_live_listing_raises_nothing(): void
    {
        $property = $this->liveListing();
        $this->giveItAWeek($property);
        $this->giveItAPhoto($property);

        $this->actingAs($this->staff())
            ->get(route('admin.properties.index'))
            ->assertOk()
            ->assertDontSee('missing something a visitor needs');
    }

    /** A draft with no photos is not a problem — nobody is paying for it to be seen. */
    public function test_a_draft_with_no_photos_is_not_flagged(): void
    {
        $this->liveListing(['status' => PropertyStatus::Draft->value]);

        $this->actingAs($this->staff())
            ->get(route('admin.properties.index'))
            ->assertOk()
            ->assertDontSee('missing something a visitor needs');
    }

    /**
     * The banner reports every live listing, not just the ones on this page of
     * the table — the whole point is that nobody had to be looking.
     */
    public function test_it_reports_a_broken_listing_from_beyond_the_first_page(): void
    {
        $broken = $this->liveListing(['title' => 'Bluewater Resort and Marina']);
        $this->giveItAWeek($broken);

        // Push it well past the 25-per-page boundary with newer, complete ones.
        for ($i = 0; $i < 30; $i++) {
            $ok = $this->liveListing(['title' => 'Complete listing '.$i]);
            $this->giveItAWeek($ok);
            $this->giveItAPhoto($ok);
        }

        $this->actingAs($this->staff())
            ->get(route('admin.properties.index'))
            ->assertOk()
            ->assertSee('Bluewater Resort and Marina');
    }
}
