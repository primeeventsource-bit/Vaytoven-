<?php

namespace Tests\Feature\Payments;

use App\Enums\BookingStatus;
use App\Enums\PaymentIntentStatus;
use App\Enums\PaymentProcessor;
use App\Models\Booking;
use App\Models\Charge;
use App\Models\PaymentIntent;
use App\Models\Refund;
use App\Services\Payments\Nmi\NmiWebhookSignatureVerifier;
use App\Services\Payments\WebhookSignatureVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class NmiWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Replace the production verifier with a no-op that just JSON-decodes
        // the payload. Tests post raw event JSON; the controller logic is what
        // we're exercising. Signature math is covered separately below.
        $this->app->singleton(WebhookSignatureVerifier::class, function () {
            return new class implements WebhookSignatureVerifier {
                public function verify(string $payload, string $signature): array
                {
                    if ($signature === 'invalid') {
                        throw new RuntimeException('bad sig');
                    }
                    return json_decode($payload, true) ?? [];
                }
            };
        });
    }

    private function postEvent(array $event): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/webhooks/nmi', $event, [
            'Webhook-Signature' => 'valid',
        ]);
    }

    public function test_invalid_signature_returns_400(): void
    {
        $this->postJson(
            '/webhooks/nmi',
            ['event_id' => 'evt_x', 'event_type' => 'transaction.sale.success'],
            ['Webhook-Signature' => 'invalid'],
        )->assertStatus(400);
    }

    public function test_missing_event_id_returns_400(): void
    {
        $this->postEvent(['event_type' => 'transaction.sale.success'])
            ->assertStatus(400);
    }

    public function test_sale_success_reconciles_lost_confirmation(): void
    {
        // Simulates: NMI approved the sale but our synchronous response was
        // lost — no Charge row exists, booking still pending.
        $booking = Booking::factory()->create(['status' => BookingStatus::PendingPayment->value]);
        $intent = PaymentIntent::factory()->create([
            'booking_id' => $booking->id,
            'external_intent_id' => 'booking:VYT-RECON1',
            'status' => 'processing',
            'amount_cents' => $booking->total_cents,
        ]);

        $this->postEvent([
            'event_id' => 'evt_sale_recon',
            'event_type' => 'transaction.sale.success',
            'event_body' => [
                'transaction_id' => '424242424242',
                'order_id' => 'booking:VYT-RECON1',
            ],
        ])->assertOk();

        $charge = Charge::where('external_charge_id', '424242424242')->first();
        $this->assertNotNull($charge);
        $this->assertSame($intent->id, $charge->payment_intent_id);
        $this->assertSame(PaymentIntentStatus::Succeeded, $intent->fresh()->status);
        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    public function test_sale_success_is_a_no_op_when_charge_already_recorded(): void
    {
        $booking = Booking::factory()->create(['status' => BookingStatus::Confirmed->value]);
        $intent = PaymentIntent::factory()->create([
            'booking_id' => $booking->id,
            'external_intent_id' => 'booking:VYT-DONE',
            'status' => 'succeeded',
        ]);
        Charge::factory()->create([
            'payment_intent_id' => $intent->id,
            'booking_id' => $booking->id,
            'external_charge_id' => '515151515151',
        ]);

        $this->postEvent([
            'event_id' => 'evt_sale_already',
            'event_type' => 'transaction.sale.success',
            'event_body' => [
                'transaction_id' => '515151515151',
                'order_id' => 'booking:VYT-DONE',
            ],
        ])->assertOk();

        $this->assertSame(1, Charge::count());
    }

    public function test_replay_of_same_event_id_is_a_no_op(): void
    {
        $booking = Booking::factory()->create(['status' => BookingStatus::PendingPayment->value]);
        PaymentIntent::factory()->create([
            'booking_id' => $booking->id,
            'external_intent_id' => 'booking:VYT-REPLAY',
            'status' => 'processing',
        ]);

        $event = [
            'event_id' => 'evt_replay_test',
            'event_type' => 'transaction.sale.success',
            'event_body' => [
                'transaction_id' => '616161616161',
                'order_id' => 'booking:VYT-REPLAY',
            ],
        ];

        $this->postEvent($event)->assertOk()->assertJsonPath('status', 'ok');
        $this->postEvent($event)->assertOk()->assertJsonPath('status', 'already_processed');

        $this->assertSame(1, DB::table('nmi_events')->where('event_id', 'evt_replay_test')->count());
        $this->assertSame(1, Charge::where('external_charge_id', '616161616161')->count());
    }

    public function test_sale_failure_marks_pending_intent_failed(): void
    {
        $intent = PaymentIntent::factory()->create([
            'external_intent_id' => 'booking:VYT-FAILS',
            'status' => 'processing',
        ]);

        $this->postEvent([
            'event_id' => 'evt_sale_failed',
            'event_type' => 'transaction.sale.failure',
            'event_body' => ['order_id' => 'booking:VYT-FAILS'],
        ])->assertOk();

        $this->assertSame(PaymentIntentStatus::Failed, $intent->fresh()->status);
    }

    public function test_sale_failure_does_not_clobber_succeeded_intent(): void
    {
        // A failure event for an earlier attempt must not downgrade an intent
        // that a later retry already settled.
        $intent = PaymentIntent::factory()->create([
            'external_intent_id' => 'booking:VYT-RETRIED',
            'status' => 'succeeded',
        ]);

        $this->postEvent([
            'event_id' => 'evt_stale_failure',
            'event_type' => 'transaction.sale.failure',
            'event_body' => ['order_id' => 'booking:VYT-RETRIED'],
        ])->assertOk();

        $this->assertSame(PaymentIntentStatus::Succeeded, $intent->fresh()->status);
    }

    public function test_refund_success_inserts_refund_row_idempotently(): void
    {
        $charge = Charge::factory()->create([
            'external_charge_id' => '717171717171',
            'amount_cents' => 38140,
        ]);

        $event = [
            'event_id' => 'evt_refund_1',
            'event_type' => 'transaction.refund.success',
            'event_body' => [
                'transaction_id' => '818181818181',
                'original_transaction_id' => '717171717171',
                'action' => ['amount' => '50.00'],
            ],
        ];

        $this->postEvent($event)->assertOk();

        $refund = Refund::where('external_refund_id', '818181818181')->first();
        $this->assertNotNull($refund);
        $this->assertSame(5000, $refund->amount_cents);
        $this->assertSame($charge->id, $refund->charge_id);

        // A second delivery with a NEW event_id but same refund transaction
        // must not double-insert (unique on processor + external_refund_id).
        $event['event_id'] = 'evt_refund_1_redelivery';
        $this->postEvent($event)->assertOk();

        $this->assertSame(1, Refund::where('external_refund_id', '818181818181')->count());
    }

    public function test_unknown_event_type_returns_200_no_op(): void
    {
        $this->postEvent([
            'event_id' => 'evt_unknown',
            'event_type' => 'settlement.batch.complete', // we don't handle this
            'event_body' => [],
        ])->assertOk();

        // Still recorded (so retries dedup) but no business effect.
        $this->assertSame(1, DB::table('nmi_events')->where('event_id', 'evt_unknown')->count());
    }

    // -----------------------------------------------------------------
    // Real signature math (not the no-op test double above).
    // -----------------------------------------------------------------

    public function test_signature_verifier_accepts_valid_hmac(): void
    {
        $verifier = new NmiWebhookSignatureVerifier('signing_key_123');
        $payload = json_encode(['event_id' => 'evt_1', 'event_type' => 'transaction.sale.success']);
        $t = time();
        $sig = hash_hmac('sha256', "{$t}.{$payload}", 'signing_key_123');

        $event = $verifier->verify($payload, "t={$t},s={$sig}");

        $this->assertSame('evt_1', $event['event_id']);
    }

    public function test_signature_verifier_rejects_bad_hmac(): void
    {
        $verifier = new NmiWebhookSignatureVerifier('signing_key_123');
        $payload = '{"event_id":"evt_1"}';
        $t = time();

        $this->expectException(RuntimeException::class);
        $verifier->verify($payload, "t={$t},s=deadbeef");
    }

    public function test_signature_verifier_rejects_stale_timestamp(): void
    {
        $verifier = new NmiWebhookSignatureVerifier('signing_key_123');
        $payload = '{"event_id":"evt_1"}';
        $t = time() - 3600; // an hour old — outside the replay window
        $sig = hash_hmac('sha256', "{$t}.{$payload}", 'signing_key_123');

        $this->expectException(RuntimeException::class);
        $verifier->verify($payload, "t={$t},s={$sig}");
    }
}
