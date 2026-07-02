<?php

namespace Tests\Feature\Payments;

use App\Enums\BookingStatus;
use App\Enums\PaymentIntentStatus;
use App\Enums\PaymentProcessor;
use App\Models\Booking;
use App\Models\Charge;
use App\Models\Refund;
use App\Services\Payments\Nmi\NmiPaymentDeclinedException;
use App\Services\Payments\Nmi\NmiService;
use App\Services\Payments\RefundBreakdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NmiServiceTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'https://secure.nmi.com/api/transact.php';

    private function fakeGateway(string $body): void
    {
        Http::fake([self::ENDPOINT => Http::response($body, 200)]);
    }

    public function test_create_payment_intent_is_local_only_and_idempotent(): void
    {
        Http::fake(); // any gateway hit would be recorded

        $booking = Booking::factory()->create(['total_cents' => 38140]);

        $service = $this->app->make(NmiService::class);
        $intent = $service->createPaymentIntent($booking);

        $this->assertSame("booking:{$booking->confirmation_code}", $intent->external_intent_id);
        $this->assertSame(38140, $intent->amount_cents);
        $this->assertSame(PaymentIntentStatus::RequiresPaymentMethod, $intent->status);
        $this->assertSame(PaymentProcessor::Nmi, $intent->processor);
        $this->assertSame($booking->id, $intent->booking_id);

        // Re-running for the same booking returns the same row, not a dupe.
        $again = $service->createPaymentIntent($booking);
        $this->assertSame($intent->id, $again->id);

        // Intent creation never talks to the gateway.
        Http::assertNothingSent();
    }

    public function test_charge_intent_posts_sale_and_confirms_booking(): void
    {
        $this->fakeGateway('response=1&responsetext=SUCCESS&authcode=123456&transactionid=9876543210&avsresponse=N&cvvresponse=M&orderid=&response_code=100');

        $booking = Booking::factory()->create([
            'total_cents' => 38140,
            'status' => BookingStatus::PendingPayment->value,
        ]);

        $service = $this->app->make(NmiService::class);
        $intent = $service->createPaymentIntent($booking);

        $charge = $service->chargeIntent($intent, 'tok_collectjs_abc');

        Http::assertSent(function (Request $request) use ($intent) {
            $data = $request->data();
            return $request->url() === self::ENDPOINT
                && $data['type'] === 'sale'
                && $data['payment_token'] === 'tok_collectjs_abc'
                && $data['amount'] === '381.40'
                && $data['currency'] === 'USD'
                && $data['orderid'] === $intent->external_intent_id
                && $data['security_key'] !== '';
        });

        $this->assertSame('9876543210', $charge->external_charge_id);
        $this->assertSame(38140, $charge->amount_cents);
        $this->assertTrue($charge->captured);
        $this->assertSame(PaymentProcessor::Nmi, $charge->processor);

        $this->assertSame(PaymentIntentStatus::Succeeded, $intent->fresh()->status);
        // Synchronous confirmation — no webhook needed.
        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    public function test_charge_intent_decline_throws_and_leaves_intent_retryable(): void
    {
        $this->fakeGateway('response=2&responsetext=DECLINE&authcode=&transactionid=1111&response_code=200');

        $booking = Booking::factory()->create(['status' => BookingStatus::PendingPayment->value]);
        $service = $this->app->make(NmiService::class);
        $intent = $service->createPaymentIntent($booking);

        try {
            $service->chargeIntent($intent, 'tok_declined');
            $this->fail('Expected NmiPaymentDeclinedException');
        } catch (NmiPaymentDeclinedException $e) {
            $this->assertSame('DECLINE', $e->getMessage());
            $this->assertStringContainsString('declined', $e->friendlyMessage());
        }

        $this->assertSame(PaymentIntentStatus::RequiresPaymentMethod, $intent->fresh()->status);
        $this->assertSame(0, Charge::count());
        $this->assertSame(BookingStatus::PendingPayment, $booking->fresh()->status);
    }

    public function test_charge_intent_is_a_no_op_when_already_paid(): void
    {
        $this->fakeGateway('response=1&responsetext=SUCCESS&transactionid=5555');

        $booking = Booking::factory()->create(['status' => BookingStatus::PendingPayment->value]);
        $service = $this->app->make(NmiService::class);
        $intent = $service->createPaymentIntent($booking);

        $first = $service->chargeIntent($intent, 'tok_first');
        $second = $service->chargeIntent($intent, 'tok_double_submit');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Charge::count());
        // Only ONE sale hit the gateway.
        Http::assertSentCount(1);
    }

    public function test_refund_charge_posts_refund_and_persists(): void
    {
        $this->fakeGateway('response=1&responsetext=SUCCESS&transactionid=8888888888');

        $charge = Charge::factory()->create([
            'external_charge_id' => '9876543210',
            'amount_cents' => 38140,
        ]);

        $breakdown = new RefundBreakdown(
            subtotal_refund_cents: 30000,
            cleaning_refund_cents: 5000,
            service_fee_refund_cents: 0,
            tax_refund_cents: 3140,
            tier: 'full',
        );

        $refund = $this->app->make(NmiService::class)
            ->refundCharge($charge, $breakdown, actorUserId: null, reason: 'traveler_cancelled');

        Http::assertSent(function (Request $request) {
            $data = $request->data();
            return $data['type'] === 'refund'
                && $data['transactionid'] === '9876543210'
                && $data['amount'] === '381.40';
        });

        $this->assertSame('8888888888', $refund->external_refund_id);
        $this->assertSame(38140, $refund->amount_cents);
        $this->assertSame($charge->id, $refund->charge_id);
        $this->assertSame($charge->booking_id, $refund->booking_id);
        $this->assertSame(PaymentProcessor::Nmi, $refund->processor);
    }

    public function test_refund_charge_rejects_zero_amount(): void
    {
        Http::fake();
        $charge = Charge::factory()->create();
        $breakdown = new RefundBreakdown(0, 0, 0, 0, 'none');

        $this->expectException(\InvalidArgumentException::class);

        $this->app->make(NmiService::class)->refundCharge($charge, $breakdown);

        Http::assertNothingSent();
    }

    public function test_refund_charge_dedupes_same_amount(): void
    {
        $this->fakeGateway('response=1&transactionid=7777');

        $charge = Charge::factory()->create(['amount_cents' => 10000]);
        $breakdown = new RefundBreakdown(10000, 0, 0, 0, 'full');

        $service = $this->app->make(NmiService::class);
        $first = $service->refundCharge($charge, $breakdown);
        $second = $service->refundCharge($charge, $breakdown);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Refund::count());
        Http::assertSentCount(1);
    }

    public function test_refund_decline_throws_and_persists_nothing(): void
    {
        $this->fakeGateway('response=3&responsetext=Transaction not found&transactionid=');

        $charge = Charge::factory()->create(['amount_cents' => 10000]);
        $breakdown = new RefundBreakdown(10000, 0, 0, 0, 'full');

        $this->expectException(NmiPaymentDeclinedException::class);

        try {
            $this->app->make(NmiService::class)->refundCharge($charge, $breakdown);
        } finally {
            $this->assertSame(0, Refund::count());
        }
    }

    public function test_store_in_vault_returns_vault_id(): void
    {
        $this->fakeGateway('response=1&responsetext=Customer Added&customer_vault_id=123456789');

        $user = \App\Models\User::factory()->create();

        $vaultId = $this->app->make(NmiService::class)->storeInVault($user, 'tok_vault_me');

        Http::assertSent(function (Request $request) {
            $data = $request->data();
            return $data['customer_vault'] === 'add_customer'
                && $data['payment_token'] === 'tok_vault_me';
        });

        $this->assertSame('123456789', $vaultId);
    }

    public function test_dollars_formats_cents_without_thousands_separator(): void
    {
        $this->assertSame('381.40', NmiService::dollars(38140));
        $this->assertSame('1234567.89', NmiService::dollars(123456789));
        $this->assertSame('0.50', NmiService::dollars(50));
    }
}
