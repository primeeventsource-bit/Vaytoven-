<?php

namespace App\Http\Controllers;

use App\Mail\MemberServiceReceipt;
use App\Models\MemberServiceOrder;
use App\Services\MemberServices\MemberServicePaymentProcessor;
use App\Support\Mail\MailDeliverability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

/**
 * The secure payment page reached from the emailed link.
 *
 * Card fields on this page belong to NMI, not to Vaytoven: Collect.js swaps
 * them for hosted iframes, so the PAN, expiry and CVV are posted straight to
 * NMI and this application receives only an opaque payment_token. That keeps
 * the server out of PCI scope for cardholder data, which it has no business
 * being in.
 */
class MemberPaymentController extends Controller
{
    public function __construct(private readonly MemberServicePaymentProcessor $payments)
    {
    }

    public function show(string $reference): View
    {
        $order = $this->resolve($reference);

        if ($order->status === \App\Enums\MemberServiceOrderStatus::Paid) {
            return view('member-services.receipt', ['order' => $order]);
        }

        return view('member-services.payment', [
            'order'           => $order,
            'tokenizationKey' => config('services.nmi.tokenization_key'),
            'collectJsUrl'    => config('services.nmi.collect_js_url'),
            'payable'         => $order->isPayable(),
        ]);
    }

    public function pay(Request $request, string $reference): RedirectResponse
    {
        $order = $this->resolve($reference);

        if (! $order->isPayable()) {
            return back()->withErrors([
                'payment_token' => 'This payment link can no longer be used. Please contact us and we will issue a new one.',
            ]);
        }

        // Only the token and the billing address are accepted. There is no
        // amount field: the figure charged comes from the order row.
        $validated = $request->validate([
            'payment_token' => ['required', 'string', 'max:255'],
            'address1'      => ['nullable', 'string', 'max:120'],
            'city'          => ['nullable', 'string', 'max:80'],
            'state'         => ['nullable', 'string', 'max:40'],
            'zip'           => ['nullable', 'string', 'max:20'],
            'country'       => ['nullable', 'string', 'max:2'],
        ]);

        $order = $this->payments->charge(
            $order,
            $validated['payment_token'],
            array_merge($validated, ['ip' => $request->ip()]),
        );

        if ($order->status !== \App\Enums\MemberServiceOrderStatus::Paid) {
            return back()->withErrors([
                'payment_token' => $this->declineMessage($order->nmi_response_text),
            ]);
        }

        $this->emailReceipt($order);

        return redirect()
            ->route('member-payment.show', $order->reference)
            ->with('payment_success', true);
    }

    /**
     * 404 for a reference that does not exist.
     *
     * Not 403, and no distinction between "no such order" and "not yours":
     * either would confirm to someone guessing references which ones are real.
     */
    private function resolve(string $reference): MemberServiceOrder
    {
        return MemberServiceOrder::query()
            ->where('reference', $reference)
            ->firstOr(fn () => abort(404));
    }

    /**
     * Show the processor's reason where it is useful, but never raw.
     *
     * NMI decline text can be terse or occasionally echo submitted data; this
     * keeps the member oriented without pasting the gateway's response into
     * the page.
     */
    private function declineMessage(?string $responseText): string
    {
        $text = strtolower((string) $responseText);

        return match (true) {
            str_contains($text, 'insufficient')  => 'That card was declined for insufficient funds. Try another card.',
            str_contains($text, 'expired')       => 'That card has expired. Please check the expiry date or use another card.',
            str_contains($text, 'avs')           => 'The billing address did not match your card. Check it and try again.',
            str_contains($text, 'cvv')           => 'The security code did not match. Check it and try again.',
            str_contains($text, 'reach the payment processor') => $responseText,
            default => 'That card was declined. Please check the details or try another card.',
        };
    }

    private function emailReceipt(MemberServiceOrder $order): void
    {
        if (! MailDeliverability::isDeliverable()) {
            Log::warning('Member services receipt not emailed — mail is not deliverable.', [
                'reference' => $order->reference,
                'reason'    => MailDeliverability::reason(),
            ]);

            return;
        }

        try {
            Mail::to($order->email)->send(new MemberServiceReceipt($order));
        } catch (Throwable $e) {
            // The money is taken and the order is paid. A failed receipt email
            // must never turn a successful payment into an error page.
            Log::error('Member services receipt email failed.', [
                'reference' => $order->reference,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
