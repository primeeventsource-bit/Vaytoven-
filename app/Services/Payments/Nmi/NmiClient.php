<?php

namespace App\Services\Payments\Nmi;

use Illuminate\Http\Client\Factory as HttpFactory;

/**
 * Thin transport wrapper for NMI's Payment API (transact.php).
 *
 * NMI is a form-POST API: every operation (sale, auth, capture, void, refund,
 * Customer Vault) goes to a single endpoint with a `type`/`customer_vault`
 * discriminator and returns an URL-encoded body:
 *
 *   response=1&responsetext=SUCCESS&authcode=123456&transactionid=987...
 *
 * `response` semantics: 1 = approved, 2 = declined, 3 = error in transaction
 * data / system error. This client only handles transport + parsing; business
 * interpretation (approved vs declined) lives in NmiService.
 */
class NmiClient
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly string $securityKey,
        private readonly string $endpoint = 'https://secure.nmi.com/api/transact.php',
    ) {
    }

    /**
     * POST the given params (merged with the merchant security key) and parse
     * the URL-encoded response into an array.
     *
     * @throws NmiTransportException on HTTP/network failure or unparseable body.
     */
    public function post(array $params): array
    {
        $response = $this->http
            ->asForm()
            ->timeout(30)
            ->post($this->endpoint, array_merge($params, [
                'security_key' => $this->securityKey,
            ]));

        if ($response->failed()) {
            throw new NmiTransportException(
                "NMI transact.php returned HTTP {$response->status()}"
            );
        }

        parse_str(trim($response->body()), $parsed);

        if (! isset($parsed['response'])) {
            throw new NmiTransportException('NMI response missing `response` field: '.substr($response->body(), 0, 200));
        }

        return $parsed;
    }
}
