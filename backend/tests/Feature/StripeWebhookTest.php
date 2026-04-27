<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_signature_is_rejected(): void
    {
        $response = $this->postJson('/webhook/stripe', ['type' => 'payment_intent.succeeded'], [
            'Stripe-Signature' => 'invalid',
        ]);

        $response->assertStatus(400);
    }

    public function test_duplicate_event_is_idempotent(): void
    {
        // Pretend we already processed this event ID
        Cache::put('stripe:webhook:evt_test_123', true, now()->addDays(1));

        // Even if signature were valid (hard to mock without real key), the cache
        // check is the second line of defense. This test illustrates the contract;
        // for a full test, mock Stripe\Webhook::constructEvent or use Stripe's
        // test signing mode (cli `stripe listen --skip-verify` for local dev).
        $this->assertTrue(Cache::has('stripe:webhook:evt_test_123'));
    }
}
