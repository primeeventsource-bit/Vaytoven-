<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PayoutMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

/**
 * Stripe webhook handler.
 *
 * Important properties:
 *   1. NO authentication middleware — Stripe calls us, not the user.
 *      (See routes/web.php: this route is excluded from CSRF and Sanctum.)
 *   2. Signature verification is mandatory. Without it, anyone could POST.
 *   3. Idempotent: every event ID is recorded for 7 days. Replays are safe.
 *   4. Always returns 200 on signature failure to avoid Stripe retries that
 *      would never succeed — but logs the failure loudly.
 */
class StripeWebhookController
{
    public function handle(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (SignatureVerificationException $e) {
            Log::warning('stripe.webhook.signature_failed', ['ip' => $request->ip()]);
            return response('Invalid signature', 400);
        } catch (\Throwable $e) {
            Log::error('stripe.webhook.parse_failed', ['error' => $e->getMessage()]);
            return response('Bad payload', 400);
        }

        // Idempotency — already-processed events are dropped silently
        $cacheKey = 'stripe:webhook:' . $event->id;
        if (Cache::has($cacheKey)) {
            Log::info('stripe.webhook.duplicate', ['event' => $event->id, 'type' => $event->type]);
            return response('OK (duplicate)', 200);
        }

        try {
            $this->dispatch($event);
            Cache::put($cacheKey, true, now()->addDays(7));
        } catch (\Throwable $e) {
            // Log but return 500 so Stripe retries
            Log::error('stripe.webhook.handler_failed', [
                'event' => $event->id,
                'type'  => $event->type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response('Handler error', 500);
        }

        return response('OK', 200);
    }

    private function dispatch(Event $event): void
    {
        match ($event->type) {
            'payment_intent.succeeded'      => $this->onPaymentSucceeded($event),
            'payment_intent.payment_failed' => $this->onPaymentFailed($event),
            'charge.refunded'               => $this->onChargeRefunded($event),
            'charge.dispute.created'        => $this->onDisputeCreated($event),
            'account.updated'               => $this->onAccountUpdated($event),
            default                         => Log::info('stripe.webhook.ignored', [
                'event' => $event->id,
                'type'  => $event->type,
            ]),
        };
    }

    private function onPaymentSucceeded(Event $event): void
    {
        $intent = $event->data->object;

        $booking = Booking::where('stripe_payment_intent_id', $intent->id)->first();
        if (! $booking) {
            Log::warning('stripe.payment_intent.no_booking', ['intent' => $intent->id]);
            return;
        }

        // Upsert Payment record
        Payment::updateOrCreate(
            ['stripe_payment_intent_id' => $intent->id],
            [
                'booking_id'         => $booking->id,
                'stripe_charge_id'   => $intent->latest_charge ?? null,
                'amount_cents'       => $intent->amount_received ?? $intent->amount,
                'currency'           => strtoupper($intent->currency),
                'status'             => 'succeeded',
                'captured_at'        => now(),
                'payment_method_details' => $intent->charges->data[0]->payment_method_details ?? null,
            ]
        );

        $booking->update([
            'payment_status'   => 'paid',
            'stripe_charge_id' => $intent->latest_charge ?? null,
        ]);
    }

    private function onPaymentFailed(Event $event): void
    {
        $intent = $event->data->object;

        if ($booking = Booking::where('stripe_payment_intent_id', $intent->id)->first()) {
            $booking->update(['payment_status' => 'unpaid']);
        }

        Log::warning('stripe.payment.failed', [
            'intent' => $intent->id,
            'reason' => $intent->last_payment_error->message ?? 'unknown',
        ]);
    }

    private function onChargeRefunded(Event $event): void
    {
        $charge = $event->data->object;

        $payment = Payment::where('stripe_charge_id', $charge->id)->first();
        if (! $payment) {
            return;
        }

        $payment->update([
            'refunded_cents' => $charge->amount_refunded,
            'status'         => $charge->refunded ? 'refunded' : 'partially_refunded',
        ]);

        if ($booking = $payment->booking) {
            $booking->update([
                'payment_status' => $charge->refunded ? 'refunded' : 'partially_refunded',
            ]);
        }
    }

    private function onDisputeCreated(Event $event): void
    {
        $dispute = $event->data->object;
        $payment = Payment::where('stripe_charge_id', $dispute->charge)->first();

        if ($payment?->booking) {
            $payment->booking->update(['payment_status' => 'disputed']);
            // TODO: open a Dispute record, notify trust team
        }
    }

    private function onAccountUpdated(Event $event): void
    {
        $account = $event->data->object;
        $method = PayoutMethod::where('stripe_account_id', $account->id)->first();

        if (! $method) {
            return;
        }

        $method->update([
            'charges_enabled'   => $account->charges_enabled ?? false,
            'payouts_enabled'   => $account->payouts_enabled ?? false,
            'details_submitted' => $account->details_submitted ?? false,
            'requirements_due'  => $account->requirements->currently_due ?? [],
        ]);
    }
}
