<?php

namespace Tests\Feature\Admin;

use App\Enums\PropertyStatus;
use App\Enums\UserRole;
use App\Models\Property;
use App\Models\PropertyView;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Services\Analytics\MemberEngagementMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The login handed to an underwriter.
 *
 * An underwriter is assessing what the product does, so the account has to
 * behave like a real member's: a live advertisement and an engagement map with
 * enough history to draw pins. A working password in front of an empty
 * dashboard answers nothing, and it is the kind of thing that looks fine from
 * the command output and turns out to be blank when somebody signs in.
 *
 * So these assert what the reviewer will actually see, not what was written.
 */
class UnderwritingLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config()->set('filesystems.default', 'local');
    }

    private function provision(array $options = []): void
    {
        $this->artisan('vaytoven:underwriting-login', $options)->run();
    }

    private function member(): User
    {
        return User::where('email', 'underwriting.review@vaytoven.com')->firstOrFail();
    }

    public function test_it_creates_a_member_who_can_sign_in(): void
    {
        $this->provision();

        $user = $this->member();

        $this->assertSame(UserRole::Member, $user->role);
        $this->assertFalse((bool) $user->must_change_password, 'the reviewer should land on the dashboard');
        $this->assertNotNull($user->email_verified_at);
    }

    /** Otherwise the first sign-in is a terms wall, not the product. */
    public function test_the_current_terms_are_already_accepted(): void
    {
        $this->provision();

        $required = app(\App\Services\Legal\LegalDocumentRegistry::class)->registrationRequired();

        $this->assertSame(
            count($required),
            \App\Models\TermsAcceptance::where('user_id', $this->member()->id)->count(),
            'every currently required document should be accepted',
        );
    }

    public function test_it_produces_a_listing(): void
    {
        $this->provision();

        $property = Property::where('host_id', $this->member()->id)->firstOrFail();

        $this->assertNotSame('', trim((string) $property->title));
        $this->assertSame('Orlando', $property->city);
        $this->assertGreaterThan(0, $property->availabilityWeeks()->count());
    }

    /**
     * The map is the thing being shown. Pins need at least
     * MIN_EVENTS_PER_PIN in a city, so seeding thin data draws nothing.
     */
    public function test_the_engagement_map_actually_has_pins(): void
    {
        $this->provision();

        $property = Property::where('host_id', $this->member()->id)->firstOrFail();

        $map = app(MemberEngagementMap::class)->build(collect([$property]), 90);

        $this->assertGreaterThanOrEqual(5, count($map['pins']), 'the map should be worth looking at');
        $this->assertGreaterThan(0, $map['totals']['ad_views']);

        foreach ($map['pins'] as $pin) {
            $this->assertGreaterThanOrEqual(
                MemberEngagementMap::MIN_EVENTS_PER_PIN,
                $pin['ad_views'],
                'a pin below the threshold would be suppressed and look like a bug',
            );
        }
    }

    /** A reviewer will change the window. Both must return something. */
    public function test_the_short_and_long_windows_both_return_data(): void
    {
        $this->provision(['--days' => 45]);

        $property = Property::where('host_id', $this->member()->id)->firstOrFail();
        $map      = app(MemberEngagementMap::class);

        $week  = $map->build(collect([$property]), 7);
        $month = $map->build(collect([$property]), 30);

        $this->assertGreaterThan(0, $week['totals']['ad_views'], 'last 7 days should not be empty');
        $this->assertGreaterThan(
            $week['totals']['ad_views'],
            $month['totals']['ad_views'],
            'a wider window should show more',
        );
    }

    /** The member dashboard renders, with the map and the figure on it. */
    public function test_the_dashboard_renders_for_the_reviewer(): void
    {
        $this->provision();

        $this->actingAs($this->member())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Ad Views', false);
    }

    // --- re-running -------------------------------------------------------------------

    public function test_running_it_twice_does_not_create_a_second_listing(): void
    {
        $this->provision();
        $this->provision();

        $this->assertSame(1, Property::where('host_id', $this->member()->id)->count());
        $this->assertSame(1, User::where('email', 'underwriting.review@vaytoven.com')->count());
    }

    public function test_running_it_twice_does_not_duplicate_the_photos(): void
    {
        $this->provision();
        $property = Property::where('host_id', $this->member()->id)->firstOrFail();
        $first    = $property->photos()->count();

        $this->provision();

        $this->assertSame($first, $property->photos()->count());
    }

    // --- the honest failure ------------------------------------------------------------

    /**
     * With no library to copy from there are no photos, and the same readiness
     * rule that protects members must apply here: the listing stays off the
     * public site and the command reports failure rather than handing over a
     * login to an empty advertisement.
     */
    public function test_without_photos_the_listing_is_not_published(): void
    {
        $this->assertSame(0, \App\Models\MediaAsset::count(), 'this test needs an empty library');

        $exit = $this->artisan('vaytoven:underwriting-login')->run();

        $property = Property::where('host_id', $this->member()->id)->firstOrFail();

        $this->assertNotSame(PropertyStatus::Active, $property->status);
        $this->assertSame(1, $exit, 'it should report failure, not success');
    }

    public function test_the_tracking_hash_chain_survives_the_seeding(): void
    {
        $this->provision();

        $this->assertGreaterThan(0, TrackingEvent::count());

        // Returns the id of the first row that does not verify, or null when
        // the whole chain holds.
        $this->assertNull(TrackingEvent::verifyChain(), 'the append-only chain must stay intact');
    }

    public function test_the_views_carry_coordinates(): void
    {
        $this->provision();

        $this->assertSame(
            0,
            PropertyView::whereNull('latitude')->orWhereNull('city')->count(),
            'a view without a city or coordinates cannot become a pin',
        );
    }
}
