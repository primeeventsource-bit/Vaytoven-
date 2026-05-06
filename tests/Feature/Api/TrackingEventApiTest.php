<?php

namespace Tests\Feature\Api;

use App\Models\PpcVisitor;
use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackingEventApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonymous_post_records_event(): void
    {
        $resp = $this->postJson('/api/v1/tracking/events', [
            'event_type' => 'page_view',
            'visitor_id' => '11111111-1111-1111-1111-111111111111',
            'metadata' => ['path' => '/'],
        ]);

        $resp->assertCreated()->assertJsonStructure(['event_uuid', 'occurred_at']);

        $event = TrackingEvent::first();
        $this->assertSame('page_view', $event->event_type);
        $this->assertSame('11111111-1111-1111-1111-111111111111', $event->visitor_id);
        $this->assertNull($event->actor_user_id);
        $this->assertSame(['path' => '/'], $event->metadata);
    }

    public function test_authenticated_post_captures_actor_user_id(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/tracking/events', [
                'event_type' => 'search_performed',
                'visitor_id' => '22222222-2222-2222-2222-222222222222',
                'metadata' => ['query' => 'mountain cabin'],
            ])
            ->assertCreated();

        $this->assertSame($user->id, TrackingEvent::first()->actor_user_id);
    }

    public function test_first_touch_attribution_is_captured_into_ppc_visitors(): void
    {
        $this->postJson('/api/v1/tracking/events', [
            'event_type' => 'page_view',
            'visitor_id' => 'visitor-with-utm',
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'summer_2027',
            'gclid' => 'abc123',
        ])->assertCreated();

        $visitor = PpcVisitor::first();
        $this->assertNotNull($visitor);
        $this->assertSame('visitor-with-utm', $visitor->visitor_id);
        $this->assertSame('google', $visitor->utm_source);
        $this->assertSame('summer_2027', $visitor->utm_campaign);
        $this->assertSame('abc123', $visitor->gclid);
    }

    public function test_subsequent_visits_with_same_visitor_id_do_not_overwrite_first_touch(): void
    {
        // First visit: organic referrer, no UTM
        $this->postJson('/api/v1/tracking/events', [
            'event_type' => 'page_view',
            'visitor_id' => 'visitor-loyal',
            'utm_source' => 'google',
            'utm_campaign' => 'first_campaign',
        ])->assertCreated();

        // Second visit: different campaign — should NOT overwrite the first.
        $this->postJson('/api/v1/tracking/events', [
            'event_type' => 'page_view',
            'visitor_id' => 'visitor-loyal',
            'utm_source' => 'facebook',
            'utm_campaign' => 'second_campaign',
        ])->assertCreated();

        $this->assertSame(1, PpcVisitor::count());
        $this->assertSame('first_campaign', PpcVisitor::first()->utm_campaign);
    }

    public function test_organic_traffic_does_not_create_ppc_visitor(): void
    {
        $this->postJson('/api/v1/tracking/events', [
            'event_type' => 'page_view',
            'visitor_id' => 'organic-visitor',
        ])->assertCreated();

        $this->assertSame(0, PpcVisitor::count());
    }

    public function test_pii_keys_are_stripped_from_metadata(): void
    {
        $this->postJson('/api/v1/tracking/events', [
            'event_type' => 'page_view',
            'visitor_id' => 'visitor-pii',
            'metadata' => [
                'path' => '/checkout',
                'card_number' => '4242424242424242',  // must be filtered
                'session_id' => 'leaked',              // must be filtered
                'cvc' => '123',                        // must be filtered
                'safe_field' => 'kept',
            ],
        ])->assertCreated();

        $event = TrackingEvent::first();
        $this->assertArrayNotHasKey('card_number', $event->metadata);
        $this->assertArrayNotHasKey('session_id', $event->metadata);
        $this->assertArrayNotHasKey('cvc', $event->metadata);
        $this->assertSame('kept', $event->metadata['safe_field']);
        $this->assertSame('/checkout', $event->metadata['path']);
    }

    public function test_validation_rejects_missing_event_type(): void
    {
        $this->postJson('/api/v1/tracking/events', [
            'visitor_id' => 'foo',
        ])->assertStatus(422)->assertJsonValidationErrors(['event_type']);
    }

    public function test_consecutive_events_chain_to_each_other(): void
    {
        $this->postJson('/api/v1/tracking/events', ['event_type' => 'a'])->assertCreated();
        $this->postJson('/api/v1/tracking/events', ['event_type' => 'b'])->assertCreated();
        $this->postJson('/api/v1/tracking/events', ['event_type' => 'c'])->assertCreated();

        $rows = TrackingEvent::orderBy('id')->get();
        $this->assertCount(3, $rows);
        $this->assertSame($rows[0]->current_hash, $rows[1]->parent_hash);
        $this->assertSame($rows[1]->current_hash, $rows[2]->parent_hash);
        $this->assertNull(TrackingEvent::verifyChain());
    }
}
