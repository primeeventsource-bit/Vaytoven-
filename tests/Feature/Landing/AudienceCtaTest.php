<?php

namespace Tests\Feature\Landing;

use App\Models\TrackingEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 10: three-audience CTAs + tracking integration.
 *
 * The landing page must:
 *   1. Surface a primary CTA for each of the three audiences (traveler, host,
 *      member) so the conversion funnel has a visible entry point per persona.
 *   2. Tag every tracked CTA with data-track-audience + data-track-cta so the
 *      auto-bind script can fire `cta_click` events with consistent metadata.
 *   3. Bundle the auto-bind script that wires those data-attrs to the SDK.
 *   4. Accept the resulting `cta_click` payload at /api/v1/tracking/events
 *      with audience metadata preserved end-to-end.
 *
 * If any of these break, the funnel stops measuring — and you can't optimise
 * what you can't see.
 */
class AudienceCtaTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_includes_traveler_host_and_member_audience_tags(): void
    {
        $body = $this->get('/')->assertOk()->getContent();

        // Each audience must have at least one tagged CTA.
        $this->assertStringContainsString('data-track-audience="traveler"', $body);
        $this->assertStringContainsString('data-track-audience="host"', $body);
        $this->assertStringContainsString('data-track-audience="member"', $body);
    }

    public function test_landing_has_primary_cta_per_audience(): void
    {
        $body = $this->get('/')->assertOk()->getContent();

        // Traveler primary: search submit button.
        $this->assertMatchesRegularExpression(
            '/class="search-submit"[^>]*data-track-cta="search_submit"/s',
            $body,
            'Traveler primary CTA (search_submit) is missing or unbound.',
        );

        // Host primary: "List your property" mailto button.
        $this->assertMatchesRegularExpression(
            '/class="host-primary-cta"[^>]*data-track-cta="host_email_open"/s',
            $body,
            'Host primary CTA (host_email_open) is missing or unbound.',
        );

        // Member primary: "Get on the program" modal opener.
        $this->assertMatchesRegularExpression(
            '/class="members-cta"[^>]*data-track-cta="enquiry_open"/s',
            $body,
            'Member primary CTA (enquiry_open) is missing or unbound.',
        );
    }

    public function test_landing_destination_cards_carry_destination_metadata(): void
    {
        $body = $this->get('/')->assertOk()->getContent();

        // Auto-bind reads data-track-meta-* attrs into metadata on cta_click.
        // Destination cards forward the city slug so funnel reports show
        // which place each visitor clicked.
        $this->assertStringContainsString('data-track-meta-destination="bali"', $body);
        $this->assertStringContainsString('data-track-meta-destination="paris"', $body);
        $this->assertStringContainsString('data-track-cta="destination_select"', $body);
    }

    public function test_landing_bundles_cta_auto_bind_script(): void
    {
        $body = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString("data-track-cta", $body);
        $this->assertStringContainsString('cta_click', $body);
        $this->assertStringContainsString('window.Vaytoven', $body);
    }

    public function test_cta_click_event_with_audience_metadata_is_persisted(): void
    {
        $payload = [
            'event_type' => 'cta_click',
            'visitor_id' => '11111111-2222-3333-4444-555555555555',
            'metadata' => [
                'audience' => 'host',
                'cta'      => 'host_email_open',
            ],
        ];

        $this->postJson('/api/v1/tracking/events', $payload, [
            'X-Vaytoven-Surface' => 'web',
        ])->assertCreated();

        $event = TrackingEvent::first();
        $this->assertSame('cta_click', $event->event_type);
        $this->assertSame('host', $event->metadata['audience'] ?? null);
        $this->assertSame('host_email_open', $event->metadata['cta'] ?? null);
    }

    public function test_enquiry_submitted_conversion_event_is_persisted(): void
    {
        $payload = [
            'event_type' => 'enquiry_submitted',
            'visitor_id' => '11111111-2222-3333-4444-555555555555',
            'metadata' => [
                'audience'  => 'member',
                'reference' => 'VYT-ABCDEFGH',
            ],
        ];

        $this->postJson('/api/v1/tracking/events', $payload, [
            'X-Vaytoven-Surface' => 'web',
        ])->assertCreated();

        $event = TrackingEvent::first();
        $this->assertSame('enquiry_submitted', $event->event_type);
        $this->assertSame('member', $event->metadata['audience'] ?? null);
        $this->assertSame('VYT-ABCDEFGH', $event->metadata['reference'] ?? null);
    }
}
