<?php

namespace App\Services\Payments;

/**
 * Processor-neutral webhook signature contract. The active gateway's
 * implementation is bound in AppServiceProvider (currently NMI).
 */
interface WebhookSignatureVerifier
{
    /**
     * Verify a webhook signature. Returns the parsed event payload as an array.
     *
     * @throws \Exception if the signature is invalid or the payload is malformed.
     */
    public function verify(string $payload, string $signature): array;
}
