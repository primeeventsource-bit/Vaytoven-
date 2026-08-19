@component('mail::message')
# Payment received

Hi {{ $order->first_name }}, thank you — your Member Services activation is paid and active.

@component('mail::panel')
**{{ $order->package->label() }} Member Services Package**

{{ $order->weeks }} {{ Str::plural('week', $order->weeks) }} × ${{ $order->pricePerWeekDollars() }}

**Paid: ${{ $order->totalDollars() }} {{ $order->currency }}**

Reference: {{ $order->reference }}
@if ($order->nmi_transaction_id)
Transaction: {{ $order->nmi_transaction_id }}
@endif
@if ($order->paid_at)
Date: {{ et($order->paid_at, 'F j, Y \a\t g:ia T') }}
@endif
@endcomponent

Keep this receipt. The reference and transaction number above are what we need
if you ever have a question about this payment.

**What happens next.** A member specialist will be in touch to get your
listing built and advertised. What you have paid for is advertising and
listing services — Vaytoven advertises your property, and any stay that
results is arranged directly between you and the traveler.

Questions? Reply to this email or call {{ setting('general.support_phone', '(877) 782-9868') }}.

Thanks,<br>
{{ setting('general.site_name', 'Vaytoven') }}
@endcomponent
