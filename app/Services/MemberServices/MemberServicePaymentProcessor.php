<?php

namespace App\Services\MemberServices;

use App\Enums\MemberServiceOrderStatus;
use App\Models\MemberServiceOrder;
use App\Services\Payments\Nmi\NmiClient;
use App\Services\Payments\Nmi\NmiTransportException;
use Illuminate\Support\Facades\Log;

/**
 * Charges a Member Services activation order through NMI.
 *
 * Card data never reaches this application. Collect.js replaces the card
 * fields in the member's browser with an opaque `payment_token` posted
 * directly to NMI; all this class sends is that token plus an amount it read
 * from the ORDER — not from the request.
 *
 * That last point is the whole security model. The gateway will charge
 * whatever it is told. The only place the amount can be trusted is the row
 * that was written when the member accepted the quote.
 */
class MemberServicePaymentProcessor
{
    public function __construct(private readonly NmiClient $nmi)
    {
    }

    /**
     * Attempt a sale. Returns the refreshed order.
     *
     * Never throws on a decline — a declined card is an ordinary outcome the
     * member can retry, not an exception. Only a transport failure is
     * exceptional, and that is caught and recorded as a failure too so the
     * member sees a message rather than a stack trace.
     */
    public function charge(MemberServiceOrder $order, string $paymentToken, array $billing = []): MemberServiceOrder
    {
        $order->increment('payment_attempts');

        $params = [
            'type'           => 'sale',
            'payment_token'  => $paymentToken,

            // NMI takes a decimal string. The order's cents are the source of
            // truth; nothing from the request is used to build this.
            'amount'         => number_format($order->total_cents / 100, 2, '.', ''),
            'currency'       => $order->currency,

            'orderid'        => $order->reference,
            'order_description' => sprintf(
                'Vaytoven Member Services — %s, %d week%s',
                $order->package->label(),
                $order->weeks,
                $order->weeks === 1 ? '' : 's',
            ),

            'first_name'     => $order->first_name,
            'last_name'      => $order->last_name,
            'email'          => $order->email,
            'phone'          => $order->phone,

            // Address verification improves the interchange rate and gives the
            // chargeback rebuttal something to stand on.
            'address1'       => $billing['address1'] ?? null,
            'city'           => $billing['city'] ?? null,
            'state'          => $billing['state'] ?? null,
            'zip'            => $billing['zip'] ?? null,
            'country'        => $billing['country'] ?? 'US',

            'ipaddress'      => $billing['ip'] ?? null,
        ];

        try {
            $response = $this->nmi->post(array_filter($params, fn ($v) => $v !== null && $v !== ''));
        } catch (NmiTransportException $e) {
            Log::error('Member services payment transport failure.', [
                'reference' => $order->reference,
                'error'     => $e->getMessage(),
            ]);

            return $this->recordFailure($order, 'We could not reach the payment processor. Please try again.');
        }

        // NMI: response=1 approved, 2 declined, 3 error.
        $approved = ($response['response'] ?? null) === '1';

        if (! $approved) {
            Log::warning('Member services payment declined.', [
                'reference' => $order->reference,
                'response'  => $response['response'] ?? null,
                // Never log the token or any card field.
                'text'      => $response['responsetext'] ?? null,
            ]);

            return $this->recordFailure($order, $response['responsetext'] ?? 'Declined');
        }

        $order->forceFill([
            'status'             => MemberServiceOrderStatus::Paid,
            'paid_at'            => now(),
            'nmi_transaction_id' => $response['transactionid'] ?? null,
            'nmi_authcode'       => $response['authcode'] ?? null,
            'nmi_response_text'  => substr((string) ($response['responsetext'] ?? 'SUCCESS'), 0, 255),
        ])->save();

        return $order->refresh();
    }

    private function recordFailure(MemberServiceOrder $order, string $reason): MemberServiceOrder
    {
        $order->forceFill([
            'status'            => MemberServiceOrderStatus::Failed,
            'nmi_response_text' => substr($reason, 0, 255),
        ])->save();

        return $order->refresh();
    }
}
