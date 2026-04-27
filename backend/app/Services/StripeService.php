<?php

namespace App\Services;

use App\Exceptions\PaymentException;
use Stripe\Account;
use Stripe\AccountLink;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\StripeClient;
use Stripe\Transfer;

class StripeService
{
    private StripeClient $client;

    public function __construct()
    {
        $this->client = new StripeClient(config('services.stripe.secret'));
    }

    public function client(): StripeClient
    {
        return $this->client;
    }

    public function createPaymentIntent(
        int $amountCents,
        string $currency,
        string $paymentMethodId,
        array $metadata = [],
    ): PaymentIntent {
        try {
            return $this->client->paymentIntents->create([
                'amount'              => $amountCents,
                'currency'            => $currency,
                'payment_method'      => $paymentMethodId,
                'confirmation_method' => 'automatic',
                'confirm'             => true,
                'capture_method'      => 'manual',
                'metadata'            => $metadata,
            ]);
        } catch (ApiErrorException $e) {
            throw new PaymentException($e->getMessage(), 0, $e);
        }
    }

    public function captureIntent(string $intentId): PaymentIntent
    {
        try {
            return $this->client->paymentIntents->capture($intentId);
        } catch (ApiErrorException $e) {
            throw new PaymentException($e->getMessage(), 0, $e);
        }
    }

    public function refund(string $intentId, int $amountCents): Refund
    {
        try {
            return $this->client->refunds->create([
                'payment_intent' => $intentId,
                'amount'         => $amountCents,
            ]);
        } catch (ApiErrorException $e) {
            throw new PaymentException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Create a Connect Express account for a host.
     */
    public function createConnectAccount(string $email, string $countryCode): Account
    {
        try {
            return $this->client->accounts->create([
                'type'    => 'express',
                'email'   => $email,
                'country' => $countryCode,
                'capabilities' => [
                    'transfers'    => ['requested' => true],
                    'card_payments' => ['requested' => true],
                ],
            ]);
        } catch (ApiErrorException $e) {
            throw new PaymentException($e->getMessage(), 0, $e);
        }
    }

    public function createAccountLink(string $accountId, string $returnUrl, string $refreshUrl): AccountLink
    {
        try {
            return $this->client->accountLinks->create([
                'account'     => $accountId,
                'refresh_url' => $refreshUrl,
                'return_url'  => $returnUrl,
                'type'        => 'account_onboarding',
            ]);
        } catch (ApiErrorException $e) {
            throw new PaymentException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Transfer funds from platform to a connected host account.
     */
    public function transferToHost(string $accountId, int $amountCents, string $currency, array $metadata = []): Transfer
    {
        try {
            return $this->client->transfers->create([
                'amount'      => $amountCents,
                'currency'    => $currency,
                'destination' => $accountId,
                'metadata'    => $metadata,
            ]);
        } catch (ApiErrorException $e) {
            throw new PaymentException($e->getMessage(), 0, $e);
        }
    }
}
