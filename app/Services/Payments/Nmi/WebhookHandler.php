<?php

namespace App\Services\Payments\Nmi;

use App\Enums\BookingStatus;
use App\Enums\PaymentIntentStatus;
use App\Enums\PaymentProcessor;
use App\Models\Charge;
use App\Models\PaymentIntent;
use App\Models\Refund;
use Illuminate\Support\Facades\Log;

/**
 * Routes verified NMI webhook events to side-effects (FR-4.2, FR-4.3).
 *
 * Unlike the old Stripe flow, webhooks are NOT the primary confirmation
 * path — NmiService::chargeIntent() settles the booking synchronously in
 * the sale response. These handlers are reconciliation: they repair state
 * when the synchronous response was lost mid-flight (network blip after
 * NMI approved) and record gateway-initiated refunds.
 *
 * The CONTROLLER handles idempotency (dedup via nmi_events.event_id);
 * this class assumes the event is fresh and applies business state.
 *
 * NMI payload shape: { event_id, event_type, event_body: { transaction_id,
 * order_id, condition, action: { amount, ... }, ... } }.
 */
class WebhookHandler
{
    public function dispatch(array $event): void
    {
        $type = (string) ($event['event_type'] ?? '');
        $body = (array) ($event['event_body'] ?? []);

        match (true) {
            $type === 'transaction.sale.success' => $this->handleSaleSuccess($body),
            $type === 'transaction.sale.failure',
            $type === 'transaction.sale.unknown' => $this->handleSaleFailure($body),
            $type === 'transaction.refund.success' => $this->handleRefundSuccess($body),
            default => Log::info("nmi webhook: unhandled type {$type}"),
        };
    }

    /**
     * Reconcile an approved sale. If chargeIntent() already recorded it the
     * unique (processor, external_charge_id) lookup finds the row and this
     * no-ops; otherwise we recover the lost confirmation here.
     */
    private function handleSaleSuccess(array $body): void
    {
        $transactionId = (string) ($body['transaction_id'] ?? '');
        $orderId = (string) ($body['order_id'] ?? '');

        if ($transactionId === '') {
            Log::warning('nmi webhook: sale.success without transaction_id');
            return;
        }

        $charge = Charge::where([
            'processor' => PaymentProcessor::Nmi->value,
            'external_charge_id' => $transactionId,
        ])->first();

        if ($charge) {
            return; // already recorded synchronously
        }

        $intent = PaymentIntent::where([
            'processor' => PaymentProcessor::Nmi->value,
            'external_intent_id' => $orderId,
        ])->first();

        if (! $intent) {
            Log::warning("nmi webhook: sale.success for unknown order {$orderId}");
            return;
        }

        Charge::firstOrCreate(
            [
                'processor' => PaymentProcessor::Nmi->value,
                'external_charge_id' => $transactionId,
            ],
            [
                'payment_intent_id' => $intent->id,
                'booking_id' => $intent->booking_id,
                'amount_cents' => $intent->amount_cents,
                'currency' => strtoupper($intent->currency ?: 'USD'),
                'captured' => true,
                'metadata' => ['reconciled_via' => 'webhook'],
            ]
        );

        $intent->update(['status' => PaymentIntentStatus::Succeeded->value]);

        $booking = $intent->booking;
        if ($booking && $booking->status === BookingStatus::PendingPayment) {
            $booking->transitionTo(BookingStatus::Confirmed, actorUserId: null, reason: 'payment_succeeded');
        }
    }

    private function handleSaleFailure(array $body): void
    {
        $orderId = (string) ($body['order_id'] ?? '');

        $intent = PaymentIntent::where([
            'processor' => PaymentProcessor::Nmi->value,
            'external_intent_id' => $orderId,
        ])->first();

        // Only downgrade a still-pending intent — a later successful retry
        // must not be clobbered by the failure event of an earlier attempt.
        if ($intent && ! $intent->status->isSettled()) {
            $intent->update(['status' => PaymentIntentStatus::Failed->value]);
        }
    }

    /**
     * Record a refund issued gateway-side (e.g. ops refunding from the NMI
     * portal). Idempotent via unique (processor, external_refund_id).
     */
    private function handleRefundSuccess(array $body): void
    {
        $transactionId = (string) ($body['transaction_id'] ?? '');
        // For refunds, original_transaction_id points at the parent sale.
        $parentId = (string) ($body['original_transaction_id'] ?? $body['parent_transaction_id'] ?? '');
        $amountCents = (int) round(((float) ($body['action']['amount'] ?? 0)) * 100);

        if ($transactionId === '') {
            return;
        }

        $charge = Charge::where([
            'processor' => PaymentProcessor::Nmi->value,
            'external_charge_id' => $parentId,
        ])->first();

        if (! $charge) {
            Log::warning("nmi webhook: refund.success for unknown parent charge {$parentId}");
            return;
        }

        Refund::firstOrCreate(
            [
                'processor' => PaymentProcessor::Nmi->value,
                'external_refund_id' => $transactionId,
            ],
            [
                'charge_id' => $charge->id,
                'booking_id' => $charge->booking_id,
                'actor_user_id' => null,
                'amount_cents' => $amountCents ?: $charge->amount_cents,
                'reason' => 'gateway_refund',
            ]
        );
    }
}
