@extends('layouts.site')

@section('title', 'Secure payment · ' . $order->reference)

@section('content')
<div class="site-shell">

    <section class="site-hero">
        <div class="eyebrow">Member Services Activation</div>
        <h1>Complete your payment</h1>
    </section>

    <section class="site-section">
        <div class="site-grid cols-2" style="align-items:start;">

            {{-- Order summary. The amount is rendered from the order row and
                 there is no field anywhere on this page that can change it. --}}
            <div class="ms-summary">
                <div class="ms-summary-head">{{ $order->package->label() }} Member Services Package</div>
                <div class="ms-summary-line">
                    {{ $order->weeks }} {{ Str::plural('week', $order->weeks) }}
                    &times; ${{ $order->pricePerWeekDollars() }}
                </div>
                <div class="ms-summary-total">
                    <span>Total due</span>
                    <strong>${{ $order->totalDollars() }}</strong>
                </div>
                <div class="ms-summary-ref">
                    Reference {{ $order->reference }}
                    @if ($order->link_expires_at && $payable)
                        <br>Valid until {{ et($order->link_expires_at, 'M j, Y') }}
                    @endif
                </div>
            </div>

            <div>
                @if (session('order_created'))
                    <div class="site-alert">
                        <strong>Activation saved.</strong>
                        We've emailed a copy of this payment link to {{ $order->email }}.
                        You can pay now, or come back to it later.
                    </div>
                @endif

                @if (! $payable)
                    <div class="site-alert" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;">
                        <strong>This payment link can no longer be used.</strong>
                        @if ($order->effectiveStatus() === \App\Enums\MemberServiceOrderStatus::Expired)
                            It expired on {{ et($order->link_expires_at, 'F j, Y') }}.
                        @endif
                        Please call {{ setting('general.support_phone', '(877) 782-9868') }} or email
                        {{ setting('general.support_email', 'contact@vaytoven.com') }} and we'll issue a new one.
                    </div>
                @elseif (! $tokenizationKey)
                    {{-- Without the public tokenization key Collect.js cannot
                         load, so there is no card form. Saying so is better
                         than rendering a form that silently cannot submit. --}}
                    <div class="site-alert" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;">
                        <strong>Card payment is temporarily unavailable.</strong>
                        Please call {{ setting('general.support_phone', '(877) 782-9868') }} and we'll help you complete this activation.
                    </div>
                @else
                    @error('payment_token')
                        <div class="site-alert" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;">
                            {{ $message }}
                        </div>
                    @enderror

                    <form method="POST" action="{{ route('member-payment.pay', $order->reference) }}" id="ms-pay">
                        @csrf

                        <h2 style="font-size:19px;margin:0 0 14px;">Card details</h2>

                        {{-- These three divs are replaced by iframes served by
                             NMI. The card number, expiry and CVV are typed into
                             NMI's document, not ours, and are posted straight
                             to them — this application only ever receives the
                             opaque token below. --}}
                        <div class="site-field">
                            <label>Card number</label>
                            <div id="nmi-ccnumber" class="nmi-field"></div>
                        </div>
                        <div class="site-row-2">
                            <div class="site-field">
                                <label>Expiry</label>
                                <div id="nmi-ccexp" class="nmi-field"></div>
                            </div>
                            <div class="site-field">
                                <label>Security code</label>
                                <div id="nmi-cvv" class="nmi-field"></div>
                            </div>
                        </div>

                        <h2 style="font-size:19px;margin:26px 0 14px;">Billing address</h2>
                        <div class="site-field">
                            <label for="address1">Street address</label>
                            <input id="address1" name="address1" type="text" autocomplete="billing street-address"
                                   value="{{ old('address1') }}">
                        </div>
                        <div class="site-row-2">
                            <div class="site-field">
                                <label for="city">City</label>
                                <input id="city" name="city" type="text" autocomplete="billing address-level2"
                                       value="{{ old('city') }}">
                            </div>
                            <div class="site-field">
                                <label for="state">State</label>
                                <input id="state" name="state" type="text" autocomplete="billing address-level1"
                                       value="{{ old('state') }}">
                            </div>
                        </div>
                        <div class="site-row-2">
                            <div class="site-field">
                                <label for="zip">ZIP</label>
                                <input id="zip" name="zip" type="text" autocomplete="billing postal-code"
                                       value="{{ old('zip') }}">
                            </div>
                            <div class="site-field">
                                <label for="country">Country</label>
                                <input id="country" name="country" type="text" maxlength="2"
                                       autocomplete="billing country" value="{{ old('country', 'US') }}">
                            </div>
                        </div>

                        {{-- Filled by Collect.js immediately before submit. --}}
                        <input type="hidden" name="payment_token" id="payment_token">

                        <button type="submit" class="site-cta" id="ms-pay-btn" style="margin-top:10px;">
                            Pay ${{ $order->totalDollars() }}
                        </button>

                        <div id="ms-pay-status" class="site-note" style="margin-top:14px;"></div>
                    </form>

                    <p class="site-note">
                        Card details go directly to our payment processor over an encrypted
                        connection. Vaytoven never receives or stores your card number.
                    </p>
                @endif
            </div>
        </div>
    </section>
</div>
@endsection

@push('head')
<style>
    .ms-summary {
        background:var(--ink); color:#fff; border-radius:18px; padding:26px 28px;
        position:sticky; top:24px;
    }
    .ms-summary-head { font-family:'Fraunces',serif; font-size:20px; font-weight:600; }
    .ms-summary-line { color:rgba(255,255,255,.7); font-size:14px; margin-top:8px; }
    .ms-summary-total {
        display:flex; justify-content:space-between; align-items:baseline;
        margin-top:20px; padding-top:18px; border-top:1px solid rgba(255,255,255,.18);
    }
    .ms-summary-total span { font-size:13px; letter-spacing:.1em; text-transform:uppercase; color:rgba(255,255,255,.65); }
    .ms-summary-total strong {
        font-family:'Fraunces',serif; font-size:32px; font-weight:600;
        background:var(--gradient); -webkit-background-clip:text; background-clip:text; color:transparent;
    }
    .ms-summary-ref { margin-top:16px; font-size:12.5px; color:rgba(255,255,255,.55); }
    /* Collect.js injects an iframe into each of these. */
    .nmi-field {
        height:46px; border:1px solid var(--line); border-radius:9px;
        background:var(--bg); padding:0 6px;
    }
    .nmi-field iframe { width:100%; height:100%; border:0; display:block; }
</style>
@endpush

@push('scripts')
@if ($payable && $tokenizationKey)
    {{-- The PUBLIC tokenization key. It is safe in the page — it can only
         create tokens, never move money. The private security key stays on the
         server and is never rendered, logged, or put in a URL. --}}
    <script src="{{ $collectJsUrl }}"
            data-tokenization-key="{{ $tokenizationKey }}"
            data-variant="inline"
            data-field-ccnumber-selector="#nmi-ccnumber"
            data-field-ccexp-selector="#nmi-ccexp"
            data-field-cvv-selector="#nmi-cvv"
            data-field-ccnumber-placeholder="4111 1111 1111 1111"
            data-field-ccexp-placeholder="MM / YY"
            data-field-cvv-placeholder="CVV"
            data-custom-css='{"font-size":"15px","padding":"12px 8px","border":"0","outline":"none"}'
            defer></script>
<script>
(function () {
    var form   = document.getElementById('ms-pay');
    var button = document.getElementById('ms-pay-btn');
    var status = document.getElementById('ms-pay-status');
    var tokenField = document.getElementById('payment_token');
    if (!form) return;

    var submitting = false;

    function fail(message) {
        submitting = false;
        button.disabled = false;
        button.textContent = button.dataset.label || button.textContent;
        status.textContent = message;
        status.style.color = '#b91c1c';
    }

    form.addEventListener('submit', function (e) {
        // Already tokenized — let the real submit through.
        if (tokenField.value) { return; }

        e.preventDefault();
        if (submitting) { return; }

        if (typeof window.CollectJS === 'undefined') {
            fail('The secure card form did not load. Refresh the page, or call us and we will take it from here.');
            return;
        }

        submitting = true;
        button.dataset.label = button.textContent;
        button.disabled = true;
        button.textContent = 'Processing…';
        status.textContent = '';

        // Tokenize. The callback receives ONLY a token — the card number never
        // enters this document's scope.
        window.CollectJS.startPaymentRequest();
    });

    // Configure must run once Collect.js is on the page.
    function configure() {
        if (typeof window.CollectJS === 'undefined') {
            return window.setTimeout(configure, 120);
        }

        window.CollectJS.configure({
            variant: 'inline',
            callback: function (response) {
                if (!response || !response.token) {
                    fail('We could not read those card details. Please check them and try again.');
                    return;
                }
                tokenField.value = response.token;
                form.submit();
            },
            validationCallback: function (field, valid, message) {
                if (!valid && submitting) {
                    fail(message || 'Please check your card details.');
                }
            },
            timeoutDuration: 15000,
            timeoutCallback: function () {
                fail('The payment form timed out. Please try again.');
            }
        });
    }

    configure();
})();
</script>
@endif
@endpush
