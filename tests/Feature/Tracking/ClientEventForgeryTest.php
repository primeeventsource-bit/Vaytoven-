<?php

namespace Tests\Feature\Tracking;

use App\Enums\ActivityType;
use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What a browser is allowed to say about itself.
 *
 * The ingest endpoint is public and unauthenticated so a marketing page can
 * post a page view before anyone signs in. Everything it accepts is therefore
 * forgeable — a request sent by hand looks exactly like one sent by the site's
 * own script — and the rows it writes land in an append-only log that is read
 * during disputes.
 *
 * Append-only means no row is altered after it arrives. It says nothing about
 * whether the row was true when it arrived. These tests are the part that does.
 */
class ClientEventForgeryTest extends TestCase
{
    use RefreshDatabase;

    private function report(array $payload)
    {
        return $this->postJson('/api/v1/tracking/events', $payload);
    }

    // --- what must be refused ----------------------------------------------------

    /**
     * The whole point. Every one of these would otherwise have been accepted
     * and stored next to the genuine rows, indistinguishable from them.
     *
     * @return array<string, array{0: string}>
     */
    public static function forgeableEvents(): array
    {
        return [
            'a login that never happened'   => ['account.login_succeeded'],
            'a payment that never cleared'  => ['payment.approved'],
            'a payment submission'          => ['payment.submitted'],
            'a contract signature'          => ['member.contract_signed'],
            'an account creation'           => ['account.created'],
            'a verified email'              => ['account.email_verified'],
            'a password reset'              => ['account.password_reset'],
            'an advertisement activation'   => ['member.advertisement_activated'],
            'a document upload'             => ['member.images_uploaded'],
            'an admin action'               => ['admin.action'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('forgeableEvents')]
    public function test_a_consequential_event_cannot_be_posted_by_a_browser(string $type): void
    {
        $this->report(['event_type' => $type])
            ->assertStatus(422)
            ->assertJsonValidationErrors('event_type');

        $this->assertSame(0, TrackingEvent::where('event_type', $type)->count());
    }

    /**
     * Signing in does not earn the right to write your own history. A member
     * who can post payment.approved against their own account is exactly the
     * person with a reason to.
     */
    public function test_being_logged_in_does_not_grant_it(): void
    {
        $this->actingAs(User::factory()->create(['must_change_password' => false]));

        $this->report(['event_type' => 'payment.approved'])
            ->assertStatus(422);

        $this->assertSame(0, TrackingEvent::count());
    }

    /** An unknown string is not a free pass either. */
    public function test_an_unrecognised_event_type_is_refused(): void
    {
        $this->report(['event_type' => 'totally.made.up'])->assertStatus(422);

        $this->assertSame(0, TrackingEvent::count());
    }

    /**
     * The guarantee stated as one assertion rather than ten: nothing the
     * evidence bundle relies on may be self-reported. A future event added to
     * both lists fails here.
     */
    public function test_no_evidence_event_is_client_reportable(): void
    {
        $overlap = array_intersect(ActivityType::evidenceTrail(), ActivityType::clientReportable());

        $this->assertSame([], $overlap, 'evidence must never be self-reported: '.implode(', ', $overlap));
    }

    // --- what must still work ----------------------------------------------------

    public function test_a_page_view_is_still_accepted(): void
    {
        $this->report(['event_type' => 'page_view', 'metadata' => ['path' => '/properties']])
            ->assertStatus(201);

        $this->assertSame(1, TrackingEvent::where('event_type', 'page_view')->count());
    }

    /** The declarative data-vyt-event hooks already on the listing page. */
    public function test_the_browsing_events_the_site_actually_sends_are_accepted(): void
    {
        foreach (['gallery.opened', 'amenity.viewed', 'offer.started', 'map.opened'] as $type) {
            $this->report(['event_type' => $type])->assertStatus(201);
        }

        $this->assertSame(4, TrackingEvent::count());
    }

    public function test_an_accepted_event_still_carries_its_session_context(): void
    {
        $this->withHeader('Referer', 'https://vaytoven.com/properties/VAY-P-10582')
            ->report(['event_type' => 'map.opened'])
            ->assertStatus(201);

        $event = TrackingEvent::sole();

        $this->assertNotNull($event->session_id);
        $this->assertNotNull($event->device_type);

        // Not the ingest endpoint. Every browser-reported event posts to the
        // same URL, so recording the request path made the activity log read
        // "api/v1/tracking/events" for the whole browsing group.
        $this->assertSame('properties/VAY-P-10582', $event->path);
    }
}
