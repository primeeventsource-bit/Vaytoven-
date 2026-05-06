<?php

namespace Tests\Feature\Payments;

use App\Enums\BookingStatus;
use App\Enums\PaymentIntentStatus;
use App\Enums\PaymentProcessor;
use App\Models\Booking;
use App\Models\Charge;
use App\Models\ChargebackDispute;
use App\Models\HostPayoutAccount;
use App\Models\PaymentIntent;
use App\Models\Refund;
use App\Services\Payments\Stripe\WebhookSignatureVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Replace the production verifier with a no-op that just JSON-decodes
        // the payload. Tests post raw event JSON; the controller logic is what
        // we're exercising.
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
        return $this->postJson('/webhooks/stripe', $event, [
            'Stripe-Signature' => 'valid',
        ]);
    }

    public function test_invalid_signature_returns_400(): void
    {
        $resp = $this->postJson(
            '/webhooks/stripe',
            ['id' => 'evt_x', 'type' => 'payment_intent.succeeded'],
            ['Stripe-Signature' => 'invalid'],
        );

        $resp->assertStatus(400);
    }

    public function test_missing_event_id_returns_400(): void
    {
        $this->postEvent(['type' => 'payment_intent.succeeded'])
            ->assertStatus(400);
    }

    public function test_payment_intent_succeeded_advances_booking_to_confirmed(): void
    {
        $booking = Booking::factory()->create(['status' => BookingStatus::PendingPayment->value]);
        $intent = PaymentIntent::factory()->create([
            'booking_id' => $booking->id,
            'processor' => PaymentProcessor::Stripe->value,
            'external_intent_id' => 'pi_succeeds',
            'status' => 'processing',
        ]);

        $this->postEvent([
            'id' => 'evt_pi_succeeded_1',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_succeeds']],
        ])->assertOk();

        $this->assertSame(PaymentIntentStatus::Succeeded, $intent->fresh()->status);
        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    public function test_replay_of_same_event_id_is_a_no_op(): void
    {
        $booking = Booking::factory()->create(['status' => BookingStatus::PendingPayment->value]);
        PaymentIntent::factory()->create([
            'booking_id' => $booking->id,
            'processor' => PaymentProcessor::Stripe->value,
            'external_intent_id' => 'pi_replay',
            'status' => 'processing',
        ]);

        $event = [
            'id' => 'evt_replay_test',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_replay']],
        ];

        // First fire — processed.
        $first = $this->postEvent($event);
        $first->assertOk()->assertJsonPath('status', 'ok');

        // Second fire — same event_id, must short-circuit.
        $second = $this->postEvent($event);
        $second->assertOk()->assertJsonPath('status', 'already_processed');

        // Only one row in stripe_events.
        $this->assertSame(1, DB::table('stripe_events')->where('event_id', 'evt_replay_test')->count());
    }

    public function test_payment_intent_failed_marks_intent_failed(): void
    {
        $intent = PaymentIntent::factory()->create([
            'processor' => PaymentProcessor::Stripe->value,
            'external_intent_id' => 'pi_fails',
            'status' => 'processing',
        ]);

        $this->postEvent([
            'id' => 'evt_pi_failed',
            'type' => 'payment_intent.payment_failed',
            'data' => ['object' => ['id' => 'pi_fails']],
        ])->assertOk();

        $this->assertSame(PaymentIntentStatus::Failed, $intent->fresh()->status);
    }

    public function test_charge_refunded_inserts_refund_row_idempotently(): void
    {
        $charge = Charge::factory()->create([
            'processor' => PaymentProcessor::Stripe->value,
            'external_charge_id' => 'ch_refunded',
        ]);

        $event = [
            'id' => 'evt_ch_refunded',
            'type' => 'charge.refunded',
            'data' => ['object' => [
                'id' => 'ch_refunded',
                'refunds' => ['data' => [
                    [
                        'id' => 're_partial_1',
                        'amount' => 5000,
                        'reason' => 'requested_by_customer',
                    ],
                ]],
            ]],
        ];

        $this->postEvent($event)->assertOk();

        $this->assertSame(1, Refund::where('external_refund_id', 're_partial_1')->count());

        // Second event from a NEW Stripe event_id but same refund_id (e.g., a
        // subsequent charge.refunded for a partial refund that already landed)
        // must not double-insert thanks to the unique index on
        // (processor, external_refund_id).
        $event['id'] = 'evt_ch_refunded_round2';
        $this->postEvent($event)->assertOk();

        $this->assertSame(1, Refund::where('external_refund_id', 're_partial_1')->count());
    }

    public function test_dispute_created_records_chargeback(): void
    {
        $charge = Charge::factory()->create([
            'processor' => PaymentProcessor::Stripe->value,
            'external_charge_id' => 'ch_disputed',
        ]);

        $this->postEvent([
            'id' => 'evt_dispute',
            'type' => 'charge.dispute.created',
            'data' => ['object' => [
                'id' => 'dp_test_1',
                'charge' => 'ch_disputed',
                'amount' => 38140,
                'reason' => 'fraudulent',
                'status' => 'warning_needs_response',
                'evidence_details' => ['due_by' => now()->addDays(7)->getTimestamp()],
            ]],
        ])->assertOk();

        $dispute = ChargebackDispute::where('external_dispute_id', 'dp_test_1')->first();
        $this->assertNotNull($dispute);
        $this->assertSame($charge->booking_id, $dispute->booking_id);
        $this->assertSame(38140, $dispute->amount_cents);
        $this->assertSame('warning_needs_response', $dispute->status);
    }

    public function test_account_updated_syncs_host_payout_account(): void
    {
        $payout = HostPayoutAccount::factory()->create([
            'processor' => PaymentProcessor::Stripe->value,
            'external_account_id' => 'acct_synced',
            'status' => 'pending_kyc',
            'payouts_enabled' => false,
            'charges_enabled' => false,
        ]);

        $this->postEvent([
            'id' => 'evt_account_updated',
            'type' => 'account.updated',
            'data' => ['object' => [
                'id' => 'acct_synced',
                'payouts_enabled' => true,
                'charges_enabled' => true,
                'requirements' => ['disabled_reason' => null],
            ]],
        ])->assertOk();

        $payout->refresh();
        $this->assertTrue($payout->payouts_enabled);
        $this->assertTrue($payout->charges_enabled);
        $this->assertSame('verified', $payout->status);
    }

    public function test_unknown_event_type_returns_200_no_op(): void
    {
        $this->postEvent([
            'id' => 'evt_unknown',
            'type' => 'invoice.payment_succeeded',  // we don't handle this
            'data' => ['object' => []],
        ])->assertOk();

        // Still recorded (so retries dedup) but no business effect.
        $this->assertSame(1, DB::table('stripe_events')->where('event_id', 'evt_unknown')->count());
    }
}
