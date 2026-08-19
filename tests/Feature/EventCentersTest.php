<?php

namespace Tests\Feature;

use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Models\User;
use App\Support\EventCenters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Event Centers, and the path from a convention to a listing.
 *
 * The page is half directory and half front door. The directory half is easy to
 * get wrong in a way nobody notices — a calendar link to the wrong company, a
 * count that says five when there are none — and the front-door half is the
 * only reason the page is on Vaytoven rather than in a bookmark folder, so the
 * button that leads to filtered results is what most of this covers.
 */
class EventCentersTest extends TestCase
{
    use RefreshDatabase;

    private function listing(string $city, PropertyStatus $status = PropertyStatus::Active): Property
    {
        return Property::factory()->create([
            'host_id' => User::factory()->create()->id,
            'city'    => $city,
            'status'  => $status->value,
        ]);
    }

    public function test_the_page_lists_all_five_centers(): void
    {
        $response = $this->get(route('event-centers.index'))->assertOk();

        foreach (['McCormick Place', 'Orange County Convention Center', 'Las Vegas Convention Center',
                  'Georgia World Congress Center', 'Javits Center'] as $name) {
            $response->assertSee($name);
        }

        foreach (['Chicago', 'Orlando', 'Las Vegas', 'Atlanta', 'New York'] as $city) {
            $response->assertSee($city);
        }
    }

    public function test_every_center_offers_both_buttons(): void
    {
        $response = $this->get(route('event-centers.index'))->assertOk();

        $response->assertSeeInOrder(['View event calendar', 'Explore properties nearby']);

        foreach (EventCenters::all() as $center) {
            $response->assertSee($center['calendar_url'], false);
            $response->assertSee(
                route('properties.index', ['event_center' => $center['slug']]),
                false,
            );
        }
    }

    /**
     * Outbound links open in a new tab, so they need rel="noopener" — without
     * it the opened page gets a handle back to this one via window.opener.
     */
    public function test_calendar_links_are_safe_to_open(): void
    {
        $html = $this->get(route('event-centers.index'))->getContent();

        preg_match_all('/<a[^>]*target="_blank"[^>]*>/', $html, $matches);

        $this->assertNotEmpty($matches[0], 'the calendar links should open in a new tab');

        foreach ($matches[0] as $tag) {
            $this->assertStringContainsString('noopener', $tag);
        }
    }

    /** Every configured URL must be a real absolute https link, not a placeholder. */
    public function test_every_calendar_url_is_a_real_https_url(): void
    {
        foreach (EventCenters::all() as $center) {
            $this->assertMatchesRegularExpression(
                '#^https://#',
                $center['calendar_url'],
                $center['name'].' needs an https calendar URL',
            );

            $this->assertNotNull(
                parse_url($center['calendar_url'], PHP_URL_HOST),
                $center['name'].' has an unparseable calendar URL',
            );
        }
    }

    /**
     * www.lvcc.com is a Las Vegas cigar retailer, not the convention center,
     * and it is the obvious-looking guess. Naming it here so that a future
     * "tidy up" cannot quietly point customers at the wrong business.
     */
    public function test_the_las_vegas_link_is_not_the_cigar_shop(): void
    {
        $vegas = EventCenters::find('las-vegas-convention-center');

        $this->assertStringNotContainsString('lvcc.com', $vegas['calendar_url']);
    }

    // --- the count -----------------------------------------------------------------

    public function test_a_center_reports_how_many_advertisements_are_in_its_city(): void
    {
        $this->listing('Orlando');
        $this->listing('Orlando');
        $this->listing('Chicago');

        $this->get(route('event-centers.index'))
            ->assertOk()
            ->assertSee('2 advertisements in Orlando')
            ->assertSee('1 advertisement in Chicago');
    }

    /** An empty city says so rather than showing a bare zero. */
    public function test_a_center_with_nothing_says_so(): void
    {
        $this->get(route('event-centers.index'))
            ->assertOk()
            ->assertSee('No Atlanta advertisements yet');
    }

    /** A draft is not advertised, so it must not be counted as one. */
    public function test_the_count_ignores_listings_that_are_not_live(): void
    {
        $this->listing('Orlando', PropertyStatus::Draft);

        $this->get(route('event-centers.index'))
            ->assertOk()
            ->assertSee('No Orlando advertisements yet');
    }

    // --- the filter ----------------------------------------------------------------

    public function test_the_filter_returns_only_that_centers_city(): void
    {
        $orlando = $this->listing('Orlando');
        $chicago = $this->listing('Chicago');

        $this->get(route('properties.index', ['event_center' => 'orange-county-convention-center']))
            ->assertOk()
            ->assertSee($orlando->title)
            ->assertDontSee($chicago->title);
    }

    /** Cities are entered by hand, so the match cannot be case-sensitive. */
    public function test_the_filter_is_not_case_sensitive(): void
    {
        $listing = $this->listing('las vegas');

        $this->get(route('properties.index', ['event_center' => 'las-vegas-convention-center']))
            ->assertOk()
            ->assertSee($listing->title);
    }

    /**
     * An unknown slug must not silently fall through to an unfiltered page —
     * that shows someone every listing on the site under a filter they think
     * is applied.
     */
    public function test_an_unknown_center_does_not_silently_show_everything(): void
    {
        $listing = $this->listing('Orlando');

        $this->get(route('properties.index', ['event_center' => 'not-a-real-place']))
            ->assertOk()
            ->assertDontSee($listing->title);
    }

    public function test_the_filter_is_offered_in_the_search_rail(): void
    {
        $this->get(route('properties.index'))
            ->assertOk()
            ->assertSee('Event center area')
            ->assertSee('McCormick Place Area');
    }

    /** The applied filter is visible and removable, like every other one. */
    public function test_the_applied_filter_shows_a_removable_chip(): void
    {
        $this->get(route('properties.index', ['event_center' => 'javits-center']))
            ->assertOk()
            ->assertSee('Javits Center Area');
    }

    public function test_the_filter_survives_alongside_another_filter(): void
    {
        $cheap = $this->listing('Orlando');
        $cheap->update(['base_nightly_cents' => 20000]);

        $dear = $this->listing('Orlando');
        $dear->update(['base_nightly_cents' => 90000]);

        $this->get(route('properties.index', ['event_center' => 'orange-county-convention-center', 'max_price' => 500]))
            ->assertOk()
            ->assertSee($cheap->title)
            ->assertDontSee($dear->title);
    }

    // --- navigation ----------------------------------------------------------------

    public function test_the_tab_is_in_the_public_navigation(): void
    {
        $this->get(route('members.show'))
            ->assertOk()
            ->assertSee('Event Centers')
            ->assertSee(route('event-centers.index'), false);
    }

    /** No sign-in wall: the visitor arriving from a convention has no account. */
    public function test_a_guest_can_open_it(): void
    {
        $this->assertGuest();

        $this->get(route('event-centers.index'))->assertOk();
    }
}
