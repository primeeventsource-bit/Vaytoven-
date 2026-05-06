<?php

namespace Tests\Feature;

use App\Models\PpcVisitor;
use App\Models\TrackingEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the JS SDK at public/vyt-track.js is served and that its
 * expected request shape — visitor_id + UTM params merged into the body —
 * is accepted by the API.
 */
class TrackingSdkSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_sdk_file_exists_and_contains_expected_globals(): void
    {
        // Static file is served by the web server in prod (Caddy on Laravel Cloud).
        // We just verify the source file is shipped and has the right shape.
        $path = public_path('vyt-track.js');
        $this->assertFileExists($path);

        $contents = file_get_contents($path);
        $this->assertStringContainsString('vyt_vid', $contents);
        $this->assertStringContainsString('event_type', $contents);
        $this->assertStringContainsString('/api/v1/tracking/events', $contents);
        $this->assertStringContainsString('window.Vaytoven', $contents);
    }

    public function test_sdk_payload_shape_is_accepted_by_api(): void
    {
        // Simulate what vyt-track.js's send() function would POST.
        $payload = [
            'event_type' => 'page_view',
            'visitor_id' => '11111111-2222-3333-4444-555555555555',
            'metadata' => ['path' => '/landing'],
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'spring_2027',
            'gclid' => 'sample-gclid-abc',
        ];

        $resp = $this->postJson('/api/v1/tracking/events', $payload, [
            'X-Vaytoven-Surface' => 'web',
        ]);

        $resp->assertCreated();

        $event = TrackingEvent::first();
        $this->assertSame('page_view', $event->event_type);
        $this->assertSame('11111111-2222-3333-4444-555555555555', $event->visitor_id);

        $visitor = PpcVisitor::first();
        $this->assertNotNull($visitor);
        $this->assertSame('google', $visitor->utm_source);
        $this->assertSame('sample-gclid-abc', $visitor->gclid);
    }
}
