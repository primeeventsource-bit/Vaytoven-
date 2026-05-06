<?php

namespace App\Services\Payments\Stripe;

use Stripe\Webhook;

/**
 * Production verifier — uses Stripe's SDK to validate the t= + v1= signature
 * scheme described in https://stripe.com/docs/webhooks/signatures.
 *
 * Stripe sends the raw body + a Stripe-Signature header; we recompute the HMAC
 * with our STRIPE_WEBHOOK_SECRET and confirm it matches. Mismatches throw
 * Stripe\Exception\SignatureVerificationException, caught upstream as 400.
 */
class StripeWebhookSignatureVerifier implements WebhookSignatureVerifier
{
    public function __construct(private readonly string $secret)
    {
    }

    public function verify(string $payload, string $signature): array
    {
        $event = Webhook::constructEvent($payload, $signature, $this->secret);

        return $event->toArray();
    }
}
