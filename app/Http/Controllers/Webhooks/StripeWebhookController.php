<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Payments\Stripe\WebhookHandler;
use App\Services\Payments\Stripe\WebhookSignatureVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly WebhookSignatureVerifier $verifier,
        private readonly WebhookHandler $handler,
    ) {
    }

    /**
     * POST /webhooks/stripe (CSRF-excluded in bootstrap/app.php).
     *
     * The contract for FR-4.3 idempotency:
     *   1. Verify HMAC signature → 400 on failure.
     *   2. Insert event_id into stripe_events. The UNIQUE index on event_id
     *      makes this our atomic "claim" — if the insert succeeds, this is
     *      the first time we've seen this event, so we process it. If it
     *      fails (QueryException on unique violation), the event was already
     *      processed; respond 200 (no-op).
     *   3. Dispatch to WebhookHandler and return 200.
     *
     * Stripe retries on any non-2xx response, so we MUST return 200 even on
     * "we've seen this before" — otherwise Stripe retries indefinitely.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature') ?? '';

        try {
            $event = $this->verifier->verify($payload, $signature);
        } catch (Throwable $e) {
            Log::warning('stripe webhook: signature verification failed: '.$e->getMessage());
            return response()->json(['error' => 'invalid_signature'], 400);
        }

        $eventId = $event['id'] ?? null;
        $eventType = $event['type'] ?? '';

        if (empty($eventId)) {
            return response()->json(['error' => 'missing_event_id'], 400);
        }

        // Idempotent claim. firstOrCreate is a single SQL UPSERT-ish flow that
        // returns the existing row if event_id is taken — so we know whether
        // this is a fresh event or a replay.
        $existed = DB::table('stripe_events')->where('event_id', $eventId)->exists();

        if ($existed) {
            return response()->json(['status' => 'already_processed'], 200);
        }

        DB::table('stripe_events')->insert([
            'event_id' => $eventId,
            'event_type' => $eventType,
            'payload' => json_encode($event),
            'processed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $this->handler->dispatch($event);
        } catch (Throwable $e) {
            Log::error("stripe webhook handler threw on {$eventType}: ".$e->getMessage(), [
                'event_id' => $eventId,
                'exception' => $e,
            ]);
            // Returning 200 anyway — the event is recorded, and a re-fire would
            // hit the idempotency guard. Operator triages from logs.
            return response()->json(['status' => 'recorded_handler_failed'], 200);
        }

        return response()->json(['status' => 'ok'], 200);
    }
}
