<?php

namespace Tests\Feature;

use App\Enums\PropertyStatus;
use App\Enums\UserRole;
use App\Models\MemberEnquiry;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A member seeing their own listings.
 *
 * A member's listings can arrive by two routes: converting the enquiry they
 * submitted, which stamps converted_from_enquiry_id, or staff building one in
 * the admin builder, which sets host_id and nothing else.
 *
 * The dashboard only looked for the first, so every listing staff built for a
 * member was invisible to that member — no title, no view counts, no
 * engagement map — while the same listing showed correctly on the host
 * dashboard, which had always queried host_id. These pin both routes.
 */
class MemberDashboardListingsTest extends TestCase
{
    use RefreshDatabase;

    private function member(): User
    {
        return User::factory()->create([
            'role'                 => UserRole::Member,
            'email'                => 'listing.member@example.com',
            'must_change_password' => false,
        ]);
    }

    /** The case that was broken: staff build the listing and assign the owner. */
    public function test_a_listing_built_in_the_admin_shows_on_the_members_dashboard(): void
    {
        $member = $this->member();

        $listing = Property::factory()->create([
            'host_id' => $member->id,
            'title'   => 'Beachfront Two Bedroom Suite',
            'status'  => PropertyStatus::Active->value,
        ]);

        $this->actingAs($member)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($listing->title);
    }

    /** The route that already worked must keep working. */
    public function test_a_listing_converted_from_an_enquiry_still_shows(): void
    {
        $member = $this->member();

        $enquiry = MemberEnquiry::factory()->create(['email' => $member->email]);

        $listing = Property::factory()->create([
            'host_id'                   => User::factory()->create()->id,
            'converted_from_enquiry_id' => $enquiry->id,
            'title'                     => 'Converted Mountain Cabin',
            'status'                    => PropertyStatus::Active->value,
        ]);

        $this->actingAs($member)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($listing->title);
    }

    /** Both routes at once, without the listing appearing twice. */
    public function test_a_listing_matching_both_routes_appears_once(): void
    {
        $member  = $this->member();
        $enquiry = MemberEnquiry::factory()->create(['email' => $member->email]);

        $listing = Property::factory()->create([
            'host_id'                   => $member->id,
            'converted_from_enquiry_id' => $enquiry->id,
            'title'                     => 'Doubly Linked Villa',
            'status'                    => PropertyStatus::Active->value,
        ]);

        // Asserted on the view data, not on how many times the title appears
        // in the HTML: the dashboard legitimately prints it in the listing
        // card, the analytics row and the map data, so counting occurrences
        // would measure the layout rather than the query.
        $response = $this->actingAs($member)->get(route('dashboard'))->assertOk();

        $listings = $response->viewData('listings');

        $this->assertCount(1, $listings, 'matching both routes must not duplicate the listing');
        $this->assertSame($listing->id, $listings->first()->id);
    }

    /** Somebody else's listing is not theirs to see. */
    public function test_another_members_listing_does_not_show(): void
    {
        $member = $this->member();

        $theirs = Property::factory()->create([
            'host_id' => User::factory()->create()->id,
            'title'   => 'Somebody Elses Penthouse',
            'status'  => PropertyStatus::Active->value,
        ]);

        $this->actingAs($member)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee($theirs->title);
    }

    /** A member with nothing yet still gets a working page. */
    public function test_a_member_with_no_listings_still_loads(): void
    {
        $this->actingAs($this->member())
            ->get(route('dashboard'))
            ->assertOk();
    }

    /**
     * The advertisement address, in full and copyable. A member asked for
     * their listing URL and the title link alone does not give it to them:
     * it cannot be read out, pasted into an email or sent to somebody.
     */
    public function test_the_dashboard_shows_the_advertisement_url(): void
    {
        $member = $this->member();

        $listing = Property::factory()->create([
            'host_id' => $member->id,
            'status'  => PropertyStatus::Active->value,
        ]);

        $this->actingAs($member)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('properties.show', $listing), false);
    }

    /** A link that shows the public nothing yet has to say so. */
    public function test_a_listing_that_is_not_live_says_the_link_is_not_public(): void
    {
        $member = $this->member();

        Property::factory()->create([
            'host_id' => $member->id,
            'status'  => PropertyStatus::Draft->value,
        ]);

        $this->actingAs($member)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Not live yet');
    }

    // --- the engagement map ---------------------------------------------------------

    /**
     * The map is what a member signs in to check: where the advertising they
     * paid for is being seen. It has to be present for a listing that has had
     * no clicks yet, or a new member concludes it is broken.
     */
    public function test_the_engagement_map_shows_for_a_listing_with_no_clicks_yet(): void
    {
        $member = $this->member();

        Property::factory()->create([
            'host_id' => $member->id,
            'status'  => PropertyStatus::Active->value,
        ]);

        $this->actingAs($member)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Where your ad is getting attention');
    }

    /** It leads the page rather than sitting under a scroll at the foot of it. */
    public function test_the_map_appears_above_the_rest_of_the_dashboard(): void
    {
        $member = $this->member();

        Property::factory()->create([
            'host_id' => $member->id,
            'status'  => PropertyStatus::Active->value,
        ]);

        $body = $this->actingAs($member)->get(route('dashboard'))->assertOk()->getContent();

        $map    = strpos($body, 'Where your ad is getting attention');
        $offers = strpos($body, 'Offers');

        $this->assertNotFalse($map, 'the map should be on the page');

        if ($offers !== false) {
            $this->assertLessThan($offers, $map, 'the map should come before the offers panel');
        }
    }
}
