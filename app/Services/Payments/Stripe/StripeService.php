<?php

namespace App\Services\Payments\Stripe;

use App\Enums\PaymentIntentStatus;
use App\Enums\PaymentProcessor;
use App\Models\Booking;
use App\Models\Charge;
use App\Models\PaymentIntent;
use App\Models\Refund;
use App\Services\Payments\RefundBreakdown;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

/**
 * High-level Stripe Connect operations (FR-4.1, FR-4.2, FR-4.4).
 *
 * Wraps Stripe's SDK with our own model persistence so the rest of the app
 * doesn't depend on Stripe data shapes. The StripeClient is injected so tests
 * can swap it for a Mockery double — no real API calls in CI.
 *
 * Idempotency keys are generated per operation so a retried HTTP call (e.g.,
 * after a network blip) doesn't double-charge. Stripe deduplicates on
 * idempotency_key + endpoint within a 24h window.
 */
class StripeService
{
    public function __construct(private readonly StripeClient $stripe)
    {
    }

    /**
     * Create a Stripe PaymentIntent for a booking and persist a row in
     * payment_intents. Caller transitions the booking to 'confirmed' on
     * the `payment_intent.succeeded` webhook (handled in Phase 5C).
     */
    public function createPaymentIntent(Booking $booking, ?string $hostStripeAccountId = null): PaymentIntent
    {
        $idempotencyKey = "booking:{$booking->confirmation_code}:intent";

        $params = [
            'amount' => $booking->total_cents,
            'currency' => 'usd',
            'capture_method' => 'automatic',
            'metadata' => [
                'booking_id' => (string) $booking->id,
                'confirmation_code' => $booking->confirmation_code,
                'traveler_id' => (string) $booking->traveler_id,
                'property_id' => (string) $booking->property_id,
            ],
        ];

        // For Stripe Connect Direct Charges, on_behalf_of routes the charge
        // through the host's connected account. Falls back to platform charges
        // when the host isn't connected yet.
        if ($hostStripeAccountId) {
            $params['on_behalf_of'] = $hostStripeAccountId;
            $params['transfer_data'] = ['destination' => $hostStripeAccountId];
        }

        $intent = $this->stripe->paymentIntents->create($params, [
            'idempotency_key' => $idempotencyKey,
        ]);

        return PaymentIntent::create([
            'booking_id' => $booking->id,
            'processor' => PaymentProcessor::Stripe->value,
            'external_intent_id' => $intent->id,
            // client_secret is always present on real Stripe responses; the
            // null-coalesce keeps test fixtures that don't set it working.
            'client_secret' => $intent->client_secret ?? null,
            'amount_cents' => $intent->amount,
            'currency' => strtoupper($intent->currency),
            'status' => $this->mapIntentStatus($intent->status),
            'metadata' => $intent->metadata?->toArray() ?? [],
        ]);
    }

    /**
     * Issue a refund for a charge. Amount comes from RefundCalculator (Phase 5A).
     * Returns the persisted Refund model.
     */
    public function refundCharge(Charge $charge, RefundBreakdown $breakdown, ?int $actorUserId = null, ?string $reason = null): Refund
    {
        if ($breakdown->total_cents <= 0) {
            throw new \InvalidArgumentException('Cannot issue a zero-amount refund');
        }

        $idempotencyKey = "charge:{$charge->external_charge_id}:refund:{$breakdown->total_cents}";

        $refund = $this->stripe->refunds->create([
            'charge' => $charge->external_charge_id,
            'amount' => $breakdown->total_cents,
            'reason' => $reason ? $this->mapReason($reason) : null,
            'metadata' => [
                'booking_id' => (string) $charge->booking_id,
                'tier' => $breakdown->tier,
            ],
        ], [
            'idempotency_key' => $idempotencyKey,
        ]);

        return Refund::create([
            'charge_id' => $charge->id,
            'booking_id' => $charge->booking_id,
            'actor_user_id' => $actorUserId,
            'processor' => PaymentProcessor::Stripe->value,
            'external_refund_id' => $refund->id,
            'amount_cents' => $refund->amount,
            'reason' => $reason,
        ]);
    }

    /**
     * Map Stripe's PaymentIntent status strings to our enum.
     */
    private function mapIntentStatus(string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'succeeded'                => PaymentIntentStatus::Succeeded->value,
            'processing'               => PaymentIntentStatus::Processing->value,
            'requires_action',
            'requires_confirmation',
            'requires_capture'         => PaymentIntentStatus::RequiresAction->value,
            'requires_payment_method'  => PaymentIntentStatus::RequiresPaymentMethod->value,
            'canceled'                 => PaymentIntentStatus::Canceled->value,
            default                    => PaymentIntentStatus::Failed->value,
        };
    }

    /**
     * Stripe accepts only a small enum of refund reasons; map our strings.
     */
    private function mapReason(string $ourReason): ?string
    {
        return match (true) {
            str_contains($ourReason, 'fraud')        => 'fraudulent',
            str_contains($ourReason, 'duplicate')    => 'duplicate',
            str_contains($ourReason, 'request'),
            str_contains($ourReason, 'cancel')       => 'requested_by_customer',
            default                                  => null,
        };
    }
}
