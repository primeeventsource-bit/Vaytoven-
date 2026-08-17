<?php

namespace Tests\Feature\MemberServices;

use App\Enums\MemberServiceOrderStatus;
use App\Enums\MemberServicePackage;
use App\Mail\MemberServiceReceipt;
use App\Models\MemberServiceOrder;
use App\Services\MemberServices\MemberServiceOrderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The secure payment page and the NMI sale.
 *
 * Card data must never reach this application: Collect.js tokenizes in the
 * member's browser and the server sees only an opaque token. The amount sent
 * to the gateway comes from the ORDER, never from the request — that is what
 * stops a member paying $2.94 against a $2,694 link.
 */
class MemberServicePaymentTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $overrides = []): MemberServiceOrder
    {
        $order = app(MemberServiceOrderFactory::class)->create(
            package: MemberServicePackage::Gold,
            weeks: 6,
            member: [
                'first_name' => 'Dana', 'last_name' => 'Whitfield',
                'email' => 'dana@example.com', 'phone' => '+1 555 555 0100',
            ],
        );

        if ($overrides) {
            $order->forceFill($overrides)->save();
        }

        return $order->refresh();
    }

    private function withNmiKeys(): void
    {
        config([
            'services.nmi.security_key'     => 'test-private-key',
            'services.nmi.tokenization_key' => 'test-public-token-key',
        ]);
    }

    private function fakeNmi(array $response): void
    {
        Http::fake([
            '*transact.php' => Http::response(http_build_query($response)),
        ]);
    }

    // --- the page ---------------------------------------------------------

    public function test_the_payment_page_shows_the_locked_total(): void
    {
        $this->withNmiKeys();
        $order = $this->order();

        $this->get("/member-payment/{$order->reference}")
            ->assertOk()
            ->assertSee('$2,694.00')
            ->assertSee('Gold Member Services Package')
            ->assertSee($order->reference);
    }

    /**
     * The page must offer no way to change what is owed. An editable amount
     * on a payment page is the same hole as an amount in the activation form.
     */
    public function test_the_payment_page_has_no_editable_amount(): void
    {
        $this->withNmiKeys();
        $html = $this->get("/member-payment/{$this->order()->reference}")->assertOk()->getContent();

        foreach (['name="amount"', 'name="total"', 'name="total_cents"'] as $field) {
            $this->assertStringNotContainsString($field, $html);
        }
    }

    /** The PUBLIC key belongs in the page; the PRIVATE one never does. */
    public function test_the_page_exposes_only_the_public_tokenization_key(): void
    {
        $this->withNmiKeys();
        $html = $this->get("/member-payment/{$this->order()->reference}")->assertOk()->getContent();

        $this->assertStringContainsString('test-public-token-key', $html);
        $this->assertStringNotContainsString('test-private-key', $html);
    }

    /** An unknown reference 404s — it must not confirm which ones are real. */
    public function test_an_unknown_reference_is_not_found(): void
    {
        $this->get('/member-payment/VTN-NOTREAL')->assertNotFound();
    }

    // --- the sale ---------------------------------------------------------

    public function test_an_approved_sale_marks_the_order_paid_and_records_the_transaction(): void
    {
        $this->withNmiKeys();
        Mail::fake();
        $this->fakeNmi([
            'response' => '1', 'responsetext' => 'SUCCESS',
            'authcode' => '123456', 'transactionid' => '9876543210',
        ]);

        $order = $this->order();

        $this->post("/member-payment/{$order->reference}", [
            'payment_token' => 'tok_abc123',
            'zip' => '33401', 'country' => 'US',
        ])->assertRedirect(route('member-payment.show', $order->reference));

        $order->refresh();

        $this->assertSame(MemberServiceOrderStatus::Paid, $order->status);
        $this->assertSame('9876543210', $order->nmi_transaction_id);
        $this->assertSame('123456', $order->nmi_authcode);
        $this->assertNotNull($order->paid_at);
    }

    /**
     * The amount posted to NMI is the ORDER's, whatever the request said.
     */
    public function test_the_amount_charged_comes_from_the_order_not_the_request(): void
    {
        $this->withNmiKeys();
        Mail::fake();
        $this->fakeNmi(['response' => '1', 'responsetext' => 'SUCCESS', 'transactionid' => '1']);

        $order = $this->order();

        $this->post("/member-payment/{$order->reference}", [
            'payment_token' => 'tok_abc123',
            'amount'        => '2.94',       // the attack
            'total_cents'   => 294,
        ]);

        Http::assertSent(function ($request) {
            return $request['amount'] === '2694.00'
                && $request['payment_token'] === 'tok_abc123';
        });
    }

    /** The private key authenticates the call, and never leaves the server. */
    public function test_the_private_security_key_is_sent_to_nmi_not_to_the_browser(): void
    {
        $this->withNmiKeys();
        Mail::fake();
        $this->fakeNmi(['response' => '1', 'responsetext' => 'SUCCESS', 'transactionid' => '1']);

        $order = $this->order();
        $this->post("/member-payment/{$order->reference}", ['payment_token' => 'tok_abc123']);

        Http::assertSent(fn ($request) => ($request['security_key'] ?? null) === 'test-private-key');
    }

    public function test_a_decline_leaves_the_order_payable_so_the_member_can_retry(): void
    {
        $this->withNmiKeys();
        Mail::fake();
        $this->fakeNmi(['response' => '2', 'responsetext' => 'DECLINE - insufficient funds']);

        $order = $this->order();

        $this->from("/member-payment/{$order->reference}")
            ->post("/member-payment/{$order->reference}", ['payment_token' => 'tok_bad'])
            ->assertRedirect("/member-payment/{$order->reference}")
            ->assertSessionHasErrors('payment_token');

        $order->refresh();

        $this->assertSame(MemberServiceOrderStatus::Failed, $order->status);
        $this->assertNull($order->paid_at);
        $this->assertTrue($order->isPayable(), 'A declined card must not close the order.');
    }

    public function test_a_decline_does_not_email_a_receipt(): void
    {
        $this->withNmiKeys();
        Mail::fake();
        $this->fakeNmi(['response' => '2', 'responsetext' => 'DECLINE']);

        $this->post("/member-payment/{$this->order()->reference}", ['payment_token' => 'tok_bad']);

        Mail::assertNotSent(MemberServiceReceipt::class);
    }

    public function test_an_approved_sale_emails_a_receipt(): void
    {
        $this->withNmiKeys();
        Mail::fake();
        config([
            'mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.example.com',
            'mail.mailers.smtp.username' => 'u', 'mail.mailers.smtp.password' => 'p',
        ]);
        $this->fakeNmi(['response' => '1', 'responsetext' => 'SUCCESS', 'transactionid' => '55']);

        $this->post("/member-payment/{$this->order()->reference}", ['payment_token' => 'tok_ok']);

        Mail::assertSent(MemberServiceReceipt::class, fn ($m) => $m->hasTo('dana@example.com'));
    }

    /**
     * A failed receipt email must never turn a completed payment into an
     * error. The money has moved; the page has to say so.
     */
    public function test_a_receipt_email_failure_does_not_break_a_successful_payment(): void
    {
        $this->withNmiKeys();
        config(['mail.default' => 'log']);          // undeliverable, as in production
        app()->detectEnvironment(fn () => 'production');
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        $this->fakeNmi(['response' => '1', 'responsetext' => 'SUCCESS', 'transactionid' => '77']);

        $order = $this->order();
        $this->post("/member-payment/{$order->reference}", ['payment_token' => 'tok_ok'])
            ->assertRedirect(route('member-payment.show', $order->reference));

        $this->assertSame(MemberServiceOrderStatus::Paid, $order->refresh()->status);
    }

    // --- link lifetime ----------------------------------------------------

    public function test_an_expired_link_cannot_be_paid(): void
    {
        $this->withNmiKeys();
        $order = $this->order(['link_expires_at' => now()->subDay()]);

        $this->assertSame(MemberServiceOrderStatus::Expired, $order->effectiveStatus());

        $this->from("/member-payment/{$order->reference}")
            ->post("/member-payment/{$order->reference}", ['payment_token' => 'tok_x'])
            ->assertSessionHasErrors('payment_token');

        Http::assertNothingSent();
    }

    public function test_a_paid_order_shows_a_receipt_rather_than_the_card_form(): void
    {
        $this->withNmiKeys();
        $order = $this->order([
            'status' => MemberServiceOrderStatus::Paid->value,
            'paid_at' => now(),
            'nmi_transaction_id' => '424242',
        ]);

        $this->get("/member-payment/{$order->reference}")
            ->assertOk()
            ->assertSee('Payment received')
            ->assertSee('424242')
            ->assertDontSee('nmi-ccnumber');
    }

    /** Paying twice must be impossible, not merely discouraged. */
    public function test_a_paid_order_cannot_be_charged_again(): void
    {
        $this->withNmiKeys();
        $order = $this->order(['status' => MemberServiceOrderStatus::Paid->value, 'paid_at' => now()]);

        $this->from("/member-payment/{$order->reference}")
            ->post("/member-payment/{$order->reference}", ['payment_token' => 'tok_again'])
            ->assertSessionHasErrors('payment_token');

        Http::assertNothingSent();
    }

    public function test_repeated_declines_eventually_stop_the_order(): void
    {
        $this->withNmiKeys();
        Mail::fake();
        $this->fakeNmi(['response' => '2', 'responsetext' => 'DECLINE']);

        $order = $this->order(['payment_attempts' => MemberServiceOrder::MAX_PAYMENT_ATTEMPTS]);

        $this->from("/member-payment/{$order->reference}")
            ->post("/member-payment/{$order->reference}", ['payment_token' => 'tok_x'])
            ->assertSessionHasErrors('payment_token');

        Http::assertNothingSent();
    }

    // --- gateway trouble --------------------------------------------------

    public function test_a_gateway_outage_is_reported_without_marking_the_order_paid(): void
    {
        $this->withNmiKeys();
        Mail::fake();
        Http::fake(['*transact.php' => Http::response('', 500)]);

        $order = $this->order();

        $this->from("/member-payment/{$order->reference}")
            ->post("/member-payment/{$order->reference}", ['payment_token' => 'tok_x'])
            ->assertSessionHasErrors('payment_token');

        $this->assertNotSame(MemberServiceOrderStatus::Paid, $order->refresh()->status);
        $this->assertNull($order->paid_at);
    }

    public function test_a_payment_token_is_required(): void
    {
        $this->withNmiKeys();

        $this->post("/member-payment/{$this->order()->reference}", [])
            ->assertSessionHasErrors('payment_token');
    }
}
