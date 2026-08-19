@component('mail::message')
# Your activation is ready to pay

Hi {{ $order->first_name }},

Here is your Vaytoven Member Services activation. Nothing has been charged yet.

@component('mail::panel')
**{{ $order->package->label() }} Member Services Package**

{{ $order->weeks }} {{ Str::plural('week', $order->weeks) }} × ${{ $order->pricePerWeekDollars() }}

**Total due: ${{ $order->totalDollars() }}**

Reference: {{ $order->reference }}
@endcomponent

@component('mail::button', ['url' => $order->paymentUrl()])
Pay securely
@endcomponent

@if ($order->link_expires_at)
This link is valid until **{{ et($order->link_expires_at, 'F j, Y') }}**. If it lapses, contact us and we will issue a new one.
@endif

Your card details are entered on a secure page hosted by our payment processor — Vaytoven never sees or stores your card number.

If you did not request this, ignore this email and nothing will happen.

Questions? Reply to this email or call {{ setting('general.support_phone', '(877) 782-9868') }}.

Thanks,<br>
{{ setting('general.site_name', 'Vaytoven') }}
@endcomponent
