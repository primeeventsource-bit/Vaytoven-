<?php

namespace Tests\Feature\Admin;

use App\Enums\ActivityType;
use App\Enums\UserRole;
use App\Models\Role;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Services\Tracking\ActivityRecorder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
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
            'city'        => 'Orlando',
            'region'      => 'FL',
            'country'     => 'US',
            'session_id'  => 'SES-A71F29',
            'device_type' => 'mobile',
            'browser'     => 'Chrome',
            'referrer_host' => 'google.com',
            'subject_reference' => 'REF-PROP-AAA',
            'result'      => 'successful',
            'occurred_at' => now(),
        ], $attributes));
    }

    // --- user agent parsing ----------------------------------------------------

    /** An iPad claims "mobile" too, so tablets must be checked first. */
    public function test_a_tablet_is_not_filed_as_a_phone(): void
    {
        $ipad = 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';

        $this->assertSame('tablet', ActivityRecorder::deviceType($ipad));
    }

    public function test_device_classes(): void
    {
        $this->assertSame('mobile', ActivityRecorder::deviceType('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) Mobile/15E148'));
        $this->assertSame('desktop', ActivityRecorder::deviceType('Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120'));
        $this->assertSame('unknown', ActivityRecorder::deviceType(null));
    }

    /**
     * Edge and Chrome both claim "chrome"; Chrome and Safari both claim
     * "safari". Checking the most specific first is the difference between a
     * useful column and one that says Chrome for everything.
     */
    public function test_browser_families_are_not_all_reported_as_chrome(): void
    {
        $this->assertSame('Edge', ActivityRecorder::browser('Mozilla/5.0 Chrome/120 Safari/537.36 Edg/120'));
        $this->assertSame('Chrome', ActivityRecorder::browser('Mozilla/5.0 Chrome/120 Safari/537.36'));
        $this->assertSame('Safari', ActivityRecorder::browser('Mozilla/5.0 (Macintosh) Version/17 Safari/605'));
        $this->assertSame('Firefox', ActivityRecorder::browser('Mozilla/5.0 Firefox/121'));
    }

    /** The host only: a full referrer carries the visitor's search terms. */
    public function test_only_the_referrer_host_is_kept(): void
    {
        $this->assertSame(
            'google.com',
            ActivityRecorder::referrerHost('https://www.google.com/search?q=very+personal+query')
        );
    }

    public function test_our_own_host_reads_as_direct(): void
    {
        config(['app.url' => 'https://vaytoven.com']);

        $this->assertSame('direct', ActivityRecorder::referrerHost('https://vaytoven.com/properties'));
    }

    // --- the log screen ---------------------------------------------------------

    public function test_the_log_lists_events_with_their_audit_columns(): void
    {
        $this->event();

        $response = $this->actingAs($this->staff())
            ->get(route('admin.activity.log'))
            ->assertOk();

        $response->assertSee('73.12.44.184');
        $response->assertSee('Orlando');
        $response->assertSee('Property advertisement viewed');
        $response->assertSee('SES-A71F29');
        $response->assertSee('google.com');
    }

    /** Never described as a physical address. */
    public function test_the_log_calls_the_location_approximate(): void
    {
        $this->event();

        $this->actingAs($this->staff())
            ->get(route('admin.activity.log'))
            ->assertOk()
            ->assertSee('approximate GeoIP');
    }

    public function test_filtering_by_group_narrows_the_list(): void
    {
        $this->event(['event_type' => ActivityType::PropertyViewed->value]);
        $this->event(['event_type' => ActivityType::PaymentApproved->value, 'subject_reference' => 'REF-PAY-BBB']);

        $response = $this->actingAs($this->staff())
            ->get(route('admin.activity.log', ['group' => 'payments']))
            ->assertOk();

        // Asserted on the ROW data, not the activity label. Every label also
        // appears in the "Activity type" dropdown, so asserting on the text
        // matches the filter control and proves nothing about the results.
        $response->assertSee('REF-PAY-BBB');
        $response->assertDontSee('REF-PROP-AAA');
    }

    /**
     * An unknown group must return nothing, not everything. A typo in a URL
     * should never quietly widen an audit view.
     */
    public function test_an_unknown_group_returns_nothing(): void
    {
        $this->event();

        $this->actingAs($this->staff())
            ->get(route('admin.activity.log', ['group' => 'nonsense']))
            ->assertOk()
            ->assertSee('No activity matches these filters');
    }

    public function test_filtering_by_ip_prefix(): void
    {
        $this->event(['ip_address' => '73.12.44.184']);
        $this->event(['ip_address' => '8.8.8.8', 'subject_reference' => 'REF-PROP-ZZZ']);

        $response = $this->actingAs($this->staff())
            ->get(route('admin.activity.log', ['ip' => '73.12']))
            ->assertOk();

        $response->assertSee('REF-PROP-AAA');
        $response->assertDontSee('REF-PROP-ZZZ');
    }

    public function test_filtering_by_property_reference(): void
    {
        $this->event(['subject_reference' => 'REF-PROP-AAA']);
        $this->event(['subject_reference' => 'REF-PROP-CCC']);

        $this->actingAs($this->staff())
            ->get(route('admin.activity.log', ['subject' => 'REF-PROP-AAA']))
            ->assertOk()
            ->assertDontSee('REF-PROP-CCC');
    }

    public function test_filtering_by_member_email(): void
    {
        $member = User::factory()->create(['email' => 'sarah@example.com']);

        $this->event(['actor_user_id' => $member->id]);
        $this->event(['subject_reference' => 'REF-PROP-DDD']);

        $this->actingAs($this->staff())
            ->get(route('admin.activity.log', ['user' => 'sarah@example.com']))
            ->assertOk()
            ->assertDontSee('REF-PROP-DDD');
    }

    // --- the journey --------------------------------------------------------------

    /** A journey read backwards is not a journey. */
    public function test_a_session_reads_oldest_first(): void
    {
        $this->event(['event_type' => ActivityType::OfferSubmitted->value, 'occurred_at' => now()]);
        $this->event(['event_type' => ActivityType::WebsiteVisited->value, 'occurred_at' => now()->subMinutes(10)]);
        $this->event(['event_type' => ActivityType::GalleryOpened->value, 'occurred_at' => now()->subMinutes(5)]);

        $body = $this->actingAs($this->staff())
            ->get(route('admin.activity.session', 'SES-A71F29'))
            ->assertOk()
            ->getContent();

        $visited = strpos($body, 'Website visited');
        $gallery = strpos($body, 'Photo gallery opened');
        $offer   = strpos($body, 'Offer submitted');

        $this->assertLessThan($gallery, $visited, 'the visit should precede the gallery');
        $this->assertLessThan($offer, $gallery, 'the gallery should precede the offer');
    }

    public function test_an_unknown_session_is_not_found(): void
    {
        $this->actingAs($this->staff())
            ->get(route('admin.activity.session', 'SES-NOPE00'))
            ->assertNotFound();
    }

    // --- access --------------------------------------------------------------------

    public function test_a_member_cannot_read_the_activity_log(): void
    {
        $this->seed(RbacSeeder::class);
        $member = User::factory()->create(['role' => UserRole::Member, 'must_change_password' => false]);

        $this->actingAs($member)->get(route('admin.activity.log'))->assertForbidden();
        $this->actingAs($member)->get(route('admin.activity.session', 'SES-A71F29'))->assertForbidden();
    }

    public function test_a_visitor_cannot_read_the_activity_log(): void
    {
        $this->get(route('admin.activity.log'))->assertRedirect(route('login'));
    }
}
