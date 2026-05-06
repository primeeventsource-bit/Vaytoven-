<?php

namespace App\Services\Payments\Stripe;

use App\Enums\BookingStatus;
use App\Enums\PaymentIntentStatus;
use App\Enums\PaymentProcessor;
use App\Models\Booking;
use App\Models\Charge;
use App\Models\ChargebackDispute;
use App\Models\HostPayoutAccount;
use App\Models\PaymentIntent;
use App\Models\Refund;
use Illuminate\Support\Facades\Log;

/**
 * Routes verified Stripe events to the right side-effects (FR-4.2, FR-4.3, FR-4.5).
 *
 * The CONTROLLER is responsible for idempotency (dedups via stripe_events.event_id).
 * This class assumes the event has been deduped and validated — its job is to
 * apply business state changes.
 */
class WebhookHandler
{
    public function dispatch(array $event): void
    {
        $type = $event['type'] ?? '';
        $data = $event['data']['object'] ?? [];

        match ($type) {
            'payment_intent.succeeded'     => $this->handlePaymentIntentSucceeded($data),
            'payment_intent.payment_failed' => $this->handlePaymentIntentFailed($data),
            'charge.refunded'              => $this->handleChargeRefunded($data),
            'charge.dispute.created'       => $this->handleDisputeCreated($data),
            'account.updated'              => $this->handleAccountUpdated($data),
            default                        => Log::info("stripe webhook: unhandled type {$type}"),
        };
    }

    /**
     * Mark the payment_intent as succeeded and advance the booking from
     * pending_payment → confirmed. Idempotent: re-running on a booking that's
     * already confirmed is a no-op.
     */
    private function handlePaymentIntentSucceeded(array $intent): void
    {
        $row = PaymentIntent::where([
            'processor' => PaymentProcessor::Stripe->value,
            'external_intent_id' => $intent['id'] ?? null,
        ])->first();

        if (! $row) {
            Log::warning("stripe webhook: payment_intent.succeeded for unknown intent {$intent['id']}");
            return;
        }

        $row->update(['status' => PaymentIntentStatus::Succeeded->value]);

        $booking = $row->booking;
        if ($booking && $booking->status === BookingStatus::PendingPayment) {
            $booking->transitionTo(BookingStatus::Confirmed, actorUserId: null, reason: 'payment_succeeded');
        }
    }

    private function handlePaymentIntentFailed(array $intent): void
    {
        $row = PaymentIntent::where([
            'processor' => PaymentProcessor::Stripe->value,
            'external_intent_id' => $intent['id'] ?? null,
        ])->first();

        if ($row) {
            $row->update(['status' => PaymentIntentStatus::Failed->value]);
        }
    }

    /**
     * Stripe fires charge.refunded once per partial-or-full refund. Record a
     * refunds row keyed by external_refund_id. The unique index on
     * (processor, external_refund_id) makes this idempotent on re-fire.
     */
    private function handleChargeRefunded(array $charge): void
    {
        $chargeRow = Charge::where([
            'processor' => PaymentProcessor::Stripe->value,
            'external_charge_id' => $charge['id'] ?? null,
        ])->first();

        if (! $chargeRow) {
            Log::warning("stripe webhook: charge.refunded for unknown charge {$charge['id']}");
            return;
        }

        // Stripe sends the latest refund inside refunds.data[]; process each.
        foreach ($charge['refunds']['data'] ?? [] as $refund) {
            Refund::firstOrCreate(
                [
                    'processor' => PaymentProcessor::Stripe->value,
                    'external_refund_id' => $refund['id'],
                ],
                [
                    'charge_id' => $chargeRow->id,
                    'booking_id' => $chargeRow->booking_id,
                    'actor_user_id' => null,
                    'amount_cents' => $refund['amount'],
                    'reason' => $refund['reason'] ?? null,
                ]
            );
        }
    }

    /**
     * A new chargeback. Record it for evidence-bundle generation in Phase 6.
     */
    private function handleDisputeCreated(array $dispute): void
    {
        $charge = Charge::where([
            'processor' => PaymentProcessor::Stripe->value,
            'external_charge_id' => $dispute['charge'] ?? null,
        ])->first();

        ChargebackDispute::firstOrCreate(
            [
                'processor' => PaymentProcessor::Stripe->value,
                'external_dispute_id' => $dispute['id'],
            ],
            [
                'booking_id' => $charge?->booking_id,
                'user_id' => null,
                'amount_cents' => $dispute['amount'],
                'reason' => $dispute['reason'] ?? null,
                'status' => $this->mapDisputeStatus($dispute['status'] ?? 'needs_response'),
                'evidence_due_by' => isset($dispute['evidence_details']['due_by'])
                    ? \Carbon\CarbonImmutable::createFromTimestamp($dispute['evidence_details']['due_by'])
                    : null,
            ]
        );
    }

    /**
     * Connect account verification status changed — sync host_payout_accounts.
     */
    private function handleAccountUpdated(array $account): void
    {
        $row = HostPayoutAccount::where([
            'processor' => PaymentProcessor::Stripe->value,
            'external_account_id' => $account['id'] ?? null,
        ])->first();

        if (! $row) {
            return;
        }

        $row->update([
            'payouts_enabled' => $account['payouts_enabled'] ?? false,
            'charges_enabled' => $account['charges_enabled'] ?? false,
            'status' => match (true) {
                ($account['payouts_enabled'] ?? false) && ($account['charges_enabled'] ?? false) => 'verified',
                ($account['requirements']['disabled_reason'] ?? null) !== null => 'restricted',
                default => 'pending_kyc',
            },
            'last_synced_at' => now(),
            'metadata' => $account,
        ]);
    }

    private function mapDisputeStatus(string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'warning_needs_response' => 'warning_needs_response',
            'warning_under_review',
            'under_review'           => 'under_review',
            'charge_refunded',
            'won'                    => 'won',
            'lost'                   => 'lost',
            'warning_closed'         => 'warning_closed',
            default                  => 'needs_response',
        };
    }
}
