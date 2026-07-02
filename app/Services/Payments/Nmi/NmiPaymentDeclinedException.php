<?php

namespace App\Services\Payments\Nmi;

/**
 * The gateway processed the request but did not approve it (response=2
 * declined, or response=3 data/system error). Carries the raw gateway
 * response for logging; `friendlyMessage()` is safe to show a traveler.
 */
class NmiPaymentDeclinedException extends \RuntimeException
{
    /** @param array<string, mixed> $response */
    public function __construct(public readonly array $response)
    {
        parent::__construct((string) ($response['responsetext'] ?? 'Payment was not approved'));
    }

    public function friendlyMessage(): string
    {
        // responsetext is processor jargon ("DECLINE", "Invalid Transaction").
        // Map the common cases; fall back to a generic retry prompt.
        $text = strtoupper((string) ($this->response['responsetext'] ?? ''));

        return match (true) {
            str_contains($text, 'INSUFF')   => 'The card was declined for insufficient funds. Try a different card.',
            str_contains($text, 'DECLINE')  => 'The card was declined. Check the details or try a different card.',
            str_contains($text, 'AVS')      => 'The billing address did not match. Check the address and try again.',
            str_contains($text, 'CVV')      => 'The security code did not match. Check the CVV and try again.',
            str_contains($text, 'EXPIRED')  => 'That card has expired. Try a different card.',
            default                         => 'Payment was not approved. Check your card details and try again.',
        };
    }
}
