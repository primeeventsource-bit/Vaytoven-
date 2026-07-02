<?php

namespace App\Services\Payments\Nmi;

use App\Services\Payments\WebhookSignatureVerifier;

/**
 * Verifies NMI webhook signatures.
 *
 * NMI signs each delivery with the endpoint's signing key (shown once in the
 * merchant portal when the webhook is created) and sends:
 *
 *   Webhook-Signature: t=<unix_ts>,s=<hex hmac_sha256("<t>.<raw body>")>
 *
 * Mismatches throw, caught upstream as HTTP 400. A 5-minute tolerance guards
 * against replay of captured deliveries.
 */
class NmiWebhookSignatureVerifier implements WebhookSignatureVerifier
{
    private const TOLERANCE_SECONDS = 300;

    public function __construct(private readonly string $signingKey)
    {
    }

    public function verify(string $payload, string $signature): array
    {
        $parts = [];
        foreach (explode(',', $signature) as $piece) {
            [$k, $v] = array_pad(explode('=', trim($piece), 2), 2, '');
            $parts[$k] = $v;
        }

        $timestamp = (int) ($parts['t'] ?? 0);
        $received = (string) ($parts['s'] ?? '');

        if ($timestamp <= 0 || $received === '') {
            throw new \RuntimeException('Malformed Webhook-Signature header');
        }

        if (abs(time() - $timestamp) > self::TOLERANCE_SECONDS) {
            throw new \RuntimeException('Webhook timestamp outside tolerance window');
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", $this->signingKey);

        if (! hash_equals($expected, $received)) {
            throw new \RuntimeException('Webhook signature mismatch');
        }

        $event = json_decode($payload, true);

        if (! is_array($event)) {
            throw new \RuntimeException('Webhook payload is not valid JSON');
        }

        return $event;
    }
}
