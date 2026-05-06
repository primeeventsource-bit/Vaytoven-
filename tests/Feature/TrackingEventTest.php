<?php

namespace Tests\Feature;

use App\Models\TrackingEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class TrackingEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_an_event_auto_populates_uuid_hash_and_timestamp(): void
    {
        $event = TrackingEvent::factory()->create();

        $this->assertNotNull($event->event_uuid);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $event->event_uuid);
        $this->assertNotNull($event->occurred_at);
        $this->assertSame(64, strlen($event->current_hash));
        $this->assertSame(64, strlen($event->parent_hash));
    }

    public function test_first_event_has_zero_parent_hash(): void
    {
        $event = TrackingEvent::factory()->create();

        $this->assertSame(str_repeat('0', 64), $event->parent_hash);
    }

    public function test_subsequent_events_chain_to_previous_current_hash(): void
    {
        $first = TrackingEvent::factory()->create();
        $second = TrackingEvent::factory()->create();

        $this->assertSame($first->current_hash, $second->parent_hash);
    }

    public function test_eloquent_update_throws_runtime_exception(): void
    {
        $event = TrackingEvent::factory()->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('append-only');

        $event->event_type = 'tampered';
        $event->save();
    }

    public function test_eloquent_delete_throws_runtime_exception(): void
    {
        $event = TrackingEvent::factory()->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('append-only');

        $event->delete();
    }

    public function test_chain_verification_returns_null_when_intact(): void
    {
        TrackingEvent::factory()->count(5)->create();

        $this->assertNull(TrackingEvent::verifyChain());
    }

    public function test_chain_verification_detects_tampered_metadata(): void
    {
        TrackingEvent::factory()->count(5)->create();

        // Tamper directly via DB (bypasses model hooks) — simulates an attacker
        // with raw DB access. The hash chain detects this.
        $rows = DB::table('tracking_events')->orderBy('id')->get();
        $third = $rows[2];
        DB::table('tracking_events')->where('id', $third->id)->update([
            'metadata' => json_encode(['path' => '/tampered']),
        ]);

        $brokenAt = TrackingEvent::verifyChain();
        $this->assertSame((int) $third->id, $brokenAt);
    }
}
