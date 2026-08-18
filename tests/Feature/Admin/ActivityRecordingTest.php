<?php

namespace Tests\Feature\Admin;

use App\Enums\ActivityType;
use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The call sites actually emit events.
 *
 * The vocabulary and the Activity Center shipped first, and a log nothing
 * writes to is an empty page with good intentions. These assert the events
 * reach the table from real requests.
 */
class ActivityRecordingTest extends TestCase
{
    use RefreshDatabase;

    private function activeProperty(): Property
    {
        return Property::factory()->create([
            'host_id' => User::factory()->create()->id,
            'status'  => PropertyStatus::Active->value,
        ]);
    }

    private function events(ActivityType $type)
    {
        return TrackingEvent::where('event_type', $type->value)->get();
    }

    public function test_viewing_a_property_is_recorded_against_its_reference(): void
    {
        $property = $this->activeProperty();

        $this->get(route('properties.show', $property))->assertOk();

        $event = $this->events(ActivityType::PropertyViewed)->sole();

        $this->assertSame($property->reference, $event->subject_reference);
        $this->assertSame('property', $event->subject_type);
        $this->assertSame('successful', $event->result);
    }

    public function test_a_search_records_the_term(): void
    {
        $this->get(route('properties.index', ['q' => 'orlando']))->assertOk();

        $event = $this->events(ActivityType::SearchPerformed)->sole();

        $this->assertSame('orlando', $event->metadata['term'] ?? null);
    }

    public function test_browsing_without_a_search_records_no_search_event(): void
    {
        $this->get(route('properties.index'))->assertOk();

        $this->assertCount(0, $this->events(ActivityType::SearchPerformed));
    }

    /** Every event carries the context the log columns display. */
    public function test_recorded_events_carry_session_device_and_browser(): void
    {
        $property = $this->activeProperty();

        $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1',
            'Referer'    => 'https://www.google.com/search?q=something+private',
        ])->get(route('properties.show', $property))->assertOk();

        $event = $this->events(ActivityType::PropertyViewed)->sole();

        $this->assertNotNull($event->session_id);
        $this->assertStringStartsWith('SES-', $event->session_id);
        $this->assertSame('mobile', $event->device_type);
        $this->assertSame('Safari', $event->browser);

        // The host only — the search term in that referrer must not be stored.
        $this->assertSame('google.com', $event->referrer_host);
        $this->assertStringNotContainsString('something+private', json_encode($event->getAttributes()));
    }

    /** Events from one visit share a session, so the journey holds together. */
    public function test_events_in_one_visit_share_a_session(): void
    {
        $property = $this->activeProperty();

        $this->get(route('properties.index', ['q' => 'orlando']));
        $this->get(route('properties.show', $property));

        $sessions = TrackingEvent::whereNotNull('session_id')->pluck('session_id')->unique();

        $this->assertCount(1, $sessions, 'one visit should produce one session id');
    }

    public function test_a_logged_in_member_is_attributed(): void
    {
        $member = User::factory()->create(['must_change_password' => false]);
        $property = $this->activeProperty();

        $this->actingAs($member)->get(route('properties.show', $property))->assertOk();

        $this->assertSame($member->id, $this->events(ActivityType::PropertyViewed)->sole()->actor_user_id);
    }

    public function test_a_guest_is_recorded_without_an_actor(): void
    {
        $this->get(route('properties.show', $this->activeProperty()))->assertOk();

        $this->assertNull($this->events(ActivityType::PropertyViewed)->sole()->actor_user_id);
    }

    // --- auth --------------------------------------------------------------------

    public function test_a_successful_login_reaches_the_activity_log(): void
    {
        $user = User::factory()->create([
            'password' => 'Str0ng-Passw0rd!',
            'must_change_password' => false,
        ]);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'Str0ng-Passw0rd!']);

        $event = $this->events(ActivityType::LoginSucceeded)->sole();

        $this->assertSame('successful', $event->result);
        $this->assertSame($user->id, $event->actor_user_id);
    }

    /** The event an auditor looks for first. */
    public function test_a_failed_login_is_recorded_as_failed(): void
    {
        $user = User::factory()->create(['password' => 'Str0ng-Passw0rd!']);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong-password']);

        $this->assertSame('failed', $this->events(ActivityType::LoginFailed)->sole()->result);
    }

    /**
     * Recording must never be able to break the request it observes.
     *
     * An audit log that can take checkout down is a worse trade than a log
     * with a gap in it.
     */
    public function test_a_recording_failure_does_not_break_the_page(): void
    {
        $property = $this->activeProperty();

        // A GeoIP service that throws is the realistic version of this: a slow
        // or broken lookup on the path of every page view.
        $this->mock(\App\Services\GeoIp\GeoIpService::class, function ($mock) {
            $mock->shouldReceive('lookup')->andThrow(new \RuntimeException('geoip is down'));
        });

        $this->get(route('properties.show', $property))->assertOk();
    }
}
