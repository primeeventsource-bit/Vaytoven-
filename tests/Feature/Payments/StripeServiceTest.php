<?php

namespace Tests\Feature\Payments;

use App\Enums\PaymentIntentStatus;
use App\Enums\PaymentProcessor;
use App\Models\Booking;
use App\Models\Charge;
use App\Services\Payments\RefundBreakdown;
use App\Services\Payments\Stripe\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Stripe\Service\PaymentIntentService;
use Stripe\Service\RefundService;
use Stripe\StripeClient;
use Tests\TestCase;

class StripeServiceTest extends TestCase
{
    use RefreshDatabase;

    private StripeClient $stripe;
    private $intents;
    private $refunds;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the StripeClient and its sub-services. The service classes are
        // public properties on StripeClient (paymentIntents, refunds, etc.) so
        // we replace each with a Mockery double and rebind the client.
        $this->intents = Mockery::mock(PaymentIntentService::class);
        $this->refunds = Mockery::mock(RefundService::class);

        $this->stripe = Mockery::mock(StripeClient::class)->makePartial();
        $this->stripe->paymentIntents = $this->intents;
        $this->stripe->refunds = $this->refunds;

        $this->app->instance(StripeClient::class, $this->stripe);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_create_payment_intent_calls_stripe_with_correct_args_and_persists(): void
    {
        $booking = Booking::factory()->create([
            'total_cents' => 38140,
        ]);

        // Stub Stripe's response.
        $stripeIntent = (object) [
            'id' => 'pi_test_abc123',
            'amount' => 38140,
            'currency' => 'usd',
            'status' => 'requires_payment_method',
            'metadata' => new class {
                public function toArray(): array { return ['booking_id' => '1']; }
            },
        ];

        $this->intents->shouldReceive('create')
            ->once()
            ->withArgs(function (array $params, array $options) use ($booking) {
                $this->assertSame(38140, $params['amount']);
                $this->assertSame('usd', $params['currency']);
                $this->assertSame((string) $booking->id, $params['metadata']['booking_id']);
                $this->assertSame(
                    "booking:{$booking->confirmation_code}:intent",
                    $options['idempotency_key']
                );
                return true;
            })
            ->andReturn($stripeIntent);

        $service = $this->app->make(StripeService::class);
        $intent = $service->createPaymentIntent($booking);

        $this->assertSame('pi_test_abc123', $intent->external_intent_id);
        $this->assertSame(38140, $intent->amount_cents);
        $this->assertSame(PaymentIntentStatus::RequiresPaymentMethod, $intent->status);
        $this->assertSame(PaymentProcessor::Stripe, $intent->processor);
        $this->assertSame($booking->id, $intent->booking_id);
    }

    public function test_create_payment_intent_includes_connect_destination_when_host_account_provided(): void
    {
        $booking = Booking::factory()->create();
        $stripeIntent = (object) [
            'id' => 'pi_test_xyz',
            'amount' => $booking->total_cents,
            'currency' => 'usd',
            'status' => 'processing',
            'metadata' => new class { public function toArray(): array { return []; } },
        ];

        $this->intents->shouldReceive('create')
            ->once()
            ->withArgs(function (array $params) {
                $this->assertSame('acct_host_demo', $params['on_behalf_of']);
                $this->assertSame('acct_host_demo', $params['transfer_data']['destination']);
                return true;
            })
            ->andReturn($stripeIntent);

        $this->app->make(StripeService::class)
            ->createPaymentIntent($booking, hostStripeAccountId: 'acct_host_demo');
    }

    public function test_refund_charge_calls_stripe_and_persists(): void
    {
        $charge = Charge::factory()->create([
            'external_charge_id' => 'ch_test_real_charge',
            'amount_cents' => 38140,
        ]);

        $breakdown = new RefundBreakdown(
            subtotal_refund_cents: 30000,
            cleaning_refund_cents: 5000,
            service_fee_refund_cents: 0,
            tax_refund_cents: 3140,
            tier: 'full',
        );

        $stripeRefund = (object) [
            'id' => 're_test_full_refund',
            'amount' => 38140,
        ];

        $this->refunds->shouldReceive('create')
            ->once()
            ->withArgs(function (array $params, array $options) {
                $this->assertSame('ch_test_real_charge', $params['charge']);
                $this->assertSame(38140, $params['amount']);
                $this->assertSame('full', $params['metadata']['tier']);
                $this->assertStringContainsString('ch_test_real_charge', $options['idempotency_key']);
                $this->assertStringContainsString('38140', $options['idempotency_key']);
                return true;
            })
            ->andReturn($stripeRefund);

        $service = $this->app->make(StripeService::class);
        $refund = $service->refundCharge($charge, $breakdown, actorUserId: null, reason: 'traveler_cancelled');

        $this->assertSame('re_test_full_refund', $refund->external_refund_id);
        $this->assertSame(38140, $refund->amount_cents);
        $this->assertSame($charge->id, $refund->charge_id);
        $this->assertSame($charge->booking_id, $refund->booking_id);
    }

    public function test_refund_charge_rejects_zero_amount(): void
    {
        $charge = Charge::factory()->create();
        $breakdown = new RefundBreakdown(0, 0, 0, 0, 'none');

        $this->expectException(\InvalidArgumentException::class);

        $this->refunds->shouldNotReceive('create');

        $this->app->make(StripeService::class)
            ->refundCharge($charge, $breakdown);
    }

    public function test_intent_status_mapping_handles_all_stripe_strings(): void
    {
        $cases = [
            'succeeded'                => PaymentIntentStatus::Succeeded,
            'processing'               => PaymentIntentStatus::Processing,
            'requires_action'          => PaymentIntentStatus::RequiresAction,
            'requires_confirmation'    => PaymentIntentStatus::RequiresAction,
            'requires_capture'         => PaymentIntentStatus::RequiresAction,
            'requires_payment_method'  => PaymentIntentStatus::RequiresPaymentMethod,
            'canceled'                 => PaymentIntentStatus::Canceled,
            'unknown_future_state'     => PaymentIntentStatus::Failed,
        ];

        foreach ($cases as $stripeStatus => $expected) {
            $booking = Booking::factory()->create();
            $stripeIntent = (object) [
                'id' => 'pi_'.uniqid(),
                'amount' => $booking->total_cents,
                'currency' => 'usd',
                'status' => $stripeStatus,
                'metadata' => new class { public function toArray(): array { return []; } },
            ];

            $this->intents->shouldReceive('create')->once()->andReturn($stripeIntent);

            $intent = $this->app->make(StripeService::class)->createPaymentIntent($booking);
            $this->assertSame($expected, $intent->status, "Status mapping failed for: {$stripeStatus}");
        }
    }
}
