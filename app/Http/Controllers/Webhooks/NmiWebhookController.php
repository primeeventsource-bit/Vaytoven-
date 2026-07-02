<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Payments\Nmi\WebhookHandler;
use App\Services\Payments\WebhookSignatureVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class NmiWebhookController extends Controller
{
    public function __construct(
        private readonly WebhookSignatureVerifier $verifier,
        private readonly WebhookHandler $handler,
    ) {
    }

    /**
     * POST /webhooks/nmi (CSRF-excluded in bootstrap/app.php).
     *
     * The contract for FR-4.3 idempotency:
     *   1. Verify HMAC signature (Webhook-Signature header) → 400 on failure.
     *   2. Insert event_id into nmi_events. The UNIQUE index on event_id is
     *      our atomic "claim" — insert succeeds means first sighting, so we
     *      process; already-exists means replay, respond 200 (no-op).
     *   3. Dispatch to WebhookHandler and return 200.
     *
     * NMI retries on non-2xx, so we return 200 even for replays and even if
     * the handler throws — the event is recorded; operator triages from logs.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Webhook-Signature') ?? '';

        try {
            $event = $this->verifier->verify($payload, $signature);
        } catch (Throwable $e) {
            Log::warning('nmi webhook: signature verification failed: '.$e->getMessage());
            return response()->json(['error' => 'invalid_signature'], 400);
        }

        $eventId = $event['event_id'] ?? null;
        $eventType = $event['event_type'] ?? '';

        if (empty($eventId)) {
            return response()->json(['error' => 'missing_event_id'], 400);
        }

        $existed = DB::table('nmi_events')->where('event_id', $eventId)->exists();

        if ($existed) {
            return response()->json(['status' => 'already_processed'], 200);
        }

        DB::table('nmi_events')->insert([
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
            Log::error("nmi webhook handler threw on {$eventType}: ".$e->getMessage(), [
                'event_id' => $eventId,
                'exception' => $e,
            ]);
            return response()->json(['status' => 'recorded_handler_failed'], 200);
        }

        return response()->json(['status' => 'ok'], 200);
    }
}
