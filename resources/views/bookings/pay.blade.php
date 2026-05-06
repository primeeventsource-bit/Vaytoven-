@extends('properties.layout')

@section('title', 'Pay for booking ' . $booking->confirmation_code)

@section('content')

    <a href="{{ route('bookings.show', $booking) }}" class="props-detail-back">← Back to booking</a>

    <div class="props-eyebrow" style="margin-top:12px;">Step 2 of 2 · Payment</div>
    <h1 class="props-title">Confirm and pay</h1>

    <div class="props-detail-grid">
        <div>
            <div class="props-card" style="display:flex; align-items:center; gap:14px; padding:16px; margin-bottom:24px;">
                <div>
                    <div class="props-card-loc" style="font-size:11px; letter-spacing:.08em; text-transform:uppercase; font-weight:600; margin-bottom:4px;">Confirmation</div>
                    <h3 style="margin:0; font-family:'SFMono-Regular',Consolas,monospace; font-size:16px; color:var(--purple);">{{ $booking->confirmation_code }}</h3>
                </div>
                <div style="margin-left:auto;">
                    <div class="props-card-loc" style="font-size:11px; letter-spacing:.08em; text-transform:uppercase; font-weight:600; margin-bottom:4px;">Total</div>
                    <div style="font-family:'Fraunces',serif; font-size:22px; font-weight:600;">${{ number_format($booking->total_cents/100, 2) }}</div>
                </div>
            </div>

            <div class="props-card" style="padding:24px;">
                <h2 style="font-family:'Fraunces',serif; font-size:18px; font-weight:600; margin:0 0 16px;">Card details</h2>

                <div id="payment-element" style="margin-bottom:18px;">
                    <p class="props-card-loc" style="font-size:13px;">Loading secure payment form…</p>
                </div>

                <div id="payment-error" role="alert" style="display:none; margin-bottom:14px; padding:12px 14px; background:#fef2f2; border:1px solid #fecaca; color:#b91c1c; border-radius:10px; font-size:13px;"></div>

                <button id="submit-payment" type="button" class="props-book-cta" style="width:100%;">
                    <span id="submit-label">Pay ${{ number_format($booking->total_cents/100, 2) }}</span>
                    <span id="submit-spinner" style="display:none;">Processing…</span>
                </button>

                <p class="props-book-fineprint" style="text-align:center; margin-top:14px;">
                    Secured by Stripe. We never see or store your card number — Stripe holds it on a PCI-DSS Level 1 compliant vault.
                </p>
            </div>
        </div>

        <aside>
            <div class="props-book-card">
                <h3 style="font-family:'Fraunces',serif; font-weight:600; margin:0 0 16px;">{{ $booking->property->title }}</h3>
                <div class="props-card-loc" style="margin-bottom:16px;">{{ $booking->property->city }}{{ $booking->property->country ? ', '.$booking->property->country : '' }}</div>

                <ul style="list-style:none; padding:0; margin:0; font-size:13px;">
                    <li style="display:flex; justify-content:space-between; padding:6px 0;">
                        <span class="props-card-loc">Check-in</span>
                        <span>{{ $booking->check_in_date->format('M j') }}</span>
                    </li>
                    <li style="display:flex; justify-content:space-between; padding:6px 0;">
                        <span class="props-card-loc">Check-out</span>
                        <span>{{ $booking->check_out_date->format('M j, Y') }}</span>
                    </li>
                    <li style="display:flex; justify-content:space-between; padding:6px 0;">
                        <span class="props-card-loc">Nights</span>
                        <span>{{ $booking->nights }}</span>
                    </li>
                    <li style="display:flex; justify-content:space-between; padding:6px 0;">
                        <span class="props-card-loc">Guests</span>
                        <span>{{ $booking->guests }}</span>
                    </li>
                </ul>

                <div style="display:flex; justify-content:space-between; padding:14px 0 0; margin-top:8px; border-top:2px solid var(--ink); font-weight:600;">
                    <span>Total</span>
                    <span style="font-family:'Fraunces',serif; font-size:18px;">${{ number_format($booking->total_cents/100, 2) }}</span>
                </div>
            </div>
        </aside>
    </div>

    <script src="https://js.stripe.com/v3/"></script>
    <script>
        (function () {
            var stripe = Stripe(@json($publishableKey));
            var clientSecret = @json($clientSecret);
            var returnUrl = @json($returnUrl);

            var elements = stripe.elements({ clientSecret: clientSecret, appearance: { theme: 'stripe' } });
            var paymentElement = elements.create('payment');
            paymentElement.mount('#payment-element');

            var submitBtn = document.getElementById('submit-payment');
            var submitLabel = document.getElementById('submit-label');
            var submitSpinner = document.getElementById('submit-spinner');
            var errorBox = document.getElementById('payment-error');

            function showError(message) {
                errorBox.textContent = message;
                errorBox.style.display = '';
            }
            function setSubmitting(isSubmitting) {
                submitBtn.disabled = isSubmitting;
                submitLabel.style.display = isSubmitting ? 'none' : '';
                submitSpinner.style.display = isSubmitting ? '' : 'none';
            }

            submitBtn.addEventListener('click', function () {
                errorBox.style.display = 'none';
                setSubmitting(true);

                stripe.confirmPayment({
                    elements: elements,
                    confirmParams: { return_url: returnUrl },
                }).then(function (result) {
                    if (result.error) {
                        showError(result.error.message || 'Payment failed. Please try again.');
                        setSubmitting(false);
                    }
                    // On success, Stripe handles the redirect to return_url itself.
                });
            });
        })();
    </script>

    <script src="/vyt-track.js" defer></script>

@endsection
