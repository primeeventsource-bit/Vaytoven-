@extends('layouts.site')

@section('title', 'Receipt · ' . $order->reference)

@section('content')
<div class="site-shell">

    <section class="site-hero">
        <div class="eyebrow">Member Services</div>
        <h1>Payment received</h1>
        <p class="lede">
            Thank you, {{ $order->first_name }}. Your Member Services activation is paid.
        </p>
    </section>

    <section class="site-section">
        <div class="site-card" style="max-width:620px;">
            <h3 style="font-family:'Fraunces',serif;font-size:20px;margin:0 0 16px;">
                {{ $order->package->label() }} Member Services Package
            </h3>

            <ul class="ms-receipt">
                <li><span>Weeks</span><span>{{ $order->weeks }} &times; ${{ $order->pricePerWeekDollars() }}</span></li>
                <li><span>Amount paid</span><span><strong>${{ $order->totalDollars() }} {{ $order->currency }}</strong></span></li>
                <li><span>Reference</span><span class="mono">{{ $order->reference }}</span></li>
                @if ($order->nmi_transaction_id)
                    <li><span>Transaction</span><span class="mono">{{ $order->nmi_transaction_id }}</span></li>
                @endif
                @if ($order->paid_at)
                    <li><span>Paid</span><span>{{ et($order->paid_at, 'F j, Y \a\t g:ia') }}</span></li>
                @endif
            </ul>

            <p class="site-note" style="margin-top:20px;">
                A copy has been emailed to {{ $order->email }}. Keep the reference and
                transaction number — they are what we need if you ever have a question
                about this payment.
            </p>
        </div>

        <div class="site-card" style="max-width:620px;margin-top:16px;">
            <h3 style="font-size:17px;margin:0 0 8px;">What happens next</h3>
            <p style="margin:0;">
                A member specialist will be in touch to get your listing built and advertised.
                What you have paid for is advertising and listing services — Vaytoven advertises
                your property, and any stay that results is arranged directly between you and
                the traveler.
            </p>
        </div>
    </section>
</div>
@endsection

@push('head')
<style>
        /* Fraunces is a variable font with an optical-size axis. On auto the
           browser picks a more decorative cut as type gets larger — flared
           serifs and exaggerated curves — which is what reads as wavy on
           headings. Pinned here; the property inherits, so this one
           declaration reaches every heading below it. */
        html { font-optical-sizing: none; }

    .ms-receipt { list-style:none; margin:0; padding:0; display:grid; gap:0; }
    .ms-receipt li {
        display:flex; justify-content:space-between; gap:16px;
        padding:11px 0; border-top:1px solid var(--line); font-size:14.5px;
    }
    .ms-receipt li:first-child { border-top:0; }
    .ms-receipt li span:first-child { color:var(--muted); }
    .ms-receipt .mono { font-family:'SFMono-Regular',Consolas,monospace; font-size:13px; }
</style>
@endpush
