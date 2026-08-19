<?php

namespace Tests\Feature;

use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Saving an advertisement.
 *
 * The wishlist tables, the favorite.saved activity type and the listing
 * analytics "saves" figure have all been in the codebase since the booking
 * product. Nothing could write one — there was no button, no route and no
 * controller — so the number was structurally zero and read on the member's
 * analytics screen as "nobody is interested" rather than as "not built".
 */
class SavedPropertiesTest extends TestCase
{
    use RefreshDatabase;

    private function member(): User
    {
        return User::factory()->create(['must_change_password' => false]);
    }

    private function listing(PropertyStatus $status = PropertyStatus::Active): Property
    {
        return Property::factory()->create([
            'host_id' => User::factory()->create()->id,
            'status'  => $status->value,
        ]);
    }

    private function savedCount(User $member): int
    {
        return DB::table('wishlist_properties')
            ->join('wishlists', 'wishlists.id', '=', 'wishlist_properties.wishlist_id')
            ->where('wishlists.user_id', $member->id)
            ->count();
    }

    public function test_a_member_can_save_a_listing(): void
    {
        $member  = $this->member();
        $listing = $this->listing();

        $this->actingAs($member)
            ->post(route('saved.toggle', $listing))
            ->assertRedirect();

        $this->assertSame(1, $this->savedCount($member));
    }

    /** The same button unsaves, so pressing it twice leaves nothing behind. */
    public function test_pressing_it_again_removes_the_save(): void
    {
        $member  = $this->member();
        $listing = $this->listing();

        $this->actingAs($member)->post(route('saved.toggle', $listing));
        $this->actingAs($member)->post(route('saved.toggle', $listing));

        $this->assertSame(0, $this->savedCount($member));
    }

    /** This is the number the member's analytics screen has always shown. */
    public function test_saving_writes_the_activity_the_analytics_count_reads(): void
    {
        $listing = $this->listing();

        $this->actingAs($this->member())->post(route('saved.toggle', $listing));

        $event = TrackingEvent::where('event_type', 'favorite.saved')->sole();

        $this->assertSame($listing->reference, $event->subject_reference);
        $this->assertSame('property', $event->subject_type);
    }

    public function test_unsaving_is_recorded_separately(): void
    {
        $member  = $this->member();
        $listing = $this->listing();

        $this->actingAs($member)->post(route('saved.toggle', $listing));
        $this->actingAs($member)->post(route('saved.toggle', $listing));

        $this->assertSame(1, TrackingEvent::where('event_type', 'favorite.saved')->count());
        $this->assertSame(1, TrackingEvent::where('event_type', 'favorite.removed')->count());
    }

    /**
     * Saving a listing that is not advertised would fill the list with entries
     * that 404 when opened.
     */
    public function test_a_listing_that_is_not_live_cannot_be_saved(): void
    {
        $this->actingAs($this->member())
            ->post(route('saved.toggle', $this->listing(PropertyStatus::Draft)))
            ->assertNotFound();
    }

    public function test_a_guest_is_sent_to_sign_in_rather_than_failing(): void
    {
        $this->post(route('saved.toggle', $this->listing()))
            ->assertRedirect(route('login'));
    }

    /** One member's list is not another's. */
    public function test_saves_are_private_to_the_member(): void
    {
        $mine    = $this->member();
        $theirs  = $this->member();
        $listing = $this->listing();

        $this->actingAs($mine)->post(route('saved.toggle', $listing));

        $this->assertSame(1, $this->savedCount($mine));
        $this->assertSame(0, $this->savedCount($theirs));
    }

    // --- the list ------------------------------------------------------------------

    public function test_the_saved_page_shows_what_was_saved(): void
    {
        $member  = $this->member();
        $listing = $this->listing();

        $this->actingAs($member)->post(route('saved.toggle', $listing));

        $this->actingAs($member)
            ->get(route('saved.index'))
            ->assertOk()
            ->assertSee($listing->title);
    }

    public function test_an_empty_list_says_so(): void
    {
        $this->actingAs($this->member())
            ->get(route('saved.index'))
            ->assertOk()
            ->assertSee('Nothing saved yet');
    }

    // --- the button ----------------------------------------------------------------

    public function test_the_listing_page_offers_a_save_button(): void
    {
        $listing = $this->listing();

        $this->actingAs($this->member())
            ->get(route('properties.show', $listing))
            ->assertOk()
            ->assertSee('Save');
    }

    /** Once saved, the button has to say so, or it invites a second press that undoes it. */
    public function test_the_button_reflects_that_it_is_already_saved(): void
    {
        $member  = $this->member();
        $listing = $this->listing();

        $this->actingAs($member)->post(route('saved.toggle', $listing));

        $this->actingAs($member)
            ->get(route('properties.show', $listing))
            ->assertOk()
            ->assertSee('Saved')
            ->assertSee('aria-pressed="true"', false);
    }

    /** A guest sees the button, but it leads to sign-in rather than doing nothing. */
    public function test_a_guest_is_offered_sign_in(): void
    {
        $listing = $this->listing();

        $this->get(route('properties.show', $listing))
            ->assertOk()
            ->assertSee(route('login', ['redirect' => 'properties/'.$listing->id]), false);
    }
}
