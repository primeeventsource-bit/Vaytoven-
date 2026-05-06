<?php

namespace App\Services\Payments\Stripe;

interface WebhookSignatureVerifier
{
    /**
     * Verify a webhook signature. Returns the parsed event payload as an array.
     *
     * @throws \Exception if the signature is invalid or the payload is malformed.
     */
    public function verify(string $payload, string $signature): array;
}
