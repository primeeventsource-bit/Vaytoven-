@extends('properties.layout')

@section('title', 'Booking ' . $booking->confirmation_code)

@section('content')

    @if (session('booking_success'))
        <div style="background:#ecfdf5; border:1px solid #a7f3d0; color:#047857; padding:14px 18px; border-radius:12px; margin-bottom:24px; font-size:14px;">
            ✓ {{ session('booking_success') }}
        </div>
    @endif

    @if ($paymentNotice)
        @php
            $tones = [
                'success' => 'background:#ecfdf5; border:1px solid #a7f3d0; color:#047857;',
                'info'    => 'background:#eff6ff; border:1px solid #bfdbfe; color:#1d4ed8;',
                'error'   => 'background:#fef2f2; border:1px solid #fecaca; color:#b91c1c;',
            ];
            $tone = $tones[$paymentNotice['tone']] ?? $tones['info'];
        @endphp
        <div style="{{ $tone }} padding:14px 18px; border-radius:12px; margin-bottom:24px; font-size:14px;">
            {{ $paymentNotice['message'] }}
        </div>
    @endif

    <a href="{{ route('properties.index') }}" class="props-detail-back">← Back to all stays</a>

    <div class="props-eyebrow" style="margin-top:12px;">Confirmation</div>
    <h1 class="props-title">{{ $booking->confirmation_code }}</h1>

    <div style="display:flex; align-items:center; gap:10px; margin-bottom:24px;">
        <span class="props-chip" style="font-size:12px; padding:5px 14px;">{{ str_replace('_', ' ', $booking->status->value) }}</span>
        @unless ($paymentsLive)
            <span style="font-size:12px; color:#92400e; background:#fffbeb; border:1px solid #fde68a; padding:5px 12px; border-radius:999px;">Payment pending</span>
        @endunless
    </div>

    <div class="props-detail-grid">
        <div>
            <div class="props-card" style="display:flex; align-items:center; gap:14px; padding:16px;">
                @if ($booking->property->photos->first())
                    <img src="{{ $booking->property->photos->first()->url }}" alt="{{ $booking->property->title }}" style="width:120px; height:90px; object-fit:cover; border-radius:8px;">
                @endif
                <div>
                    <h3 style="margin:0 0 4px; font-family:'Fraunces',serif; font-weight:600;">{{ $booking->property->title }}</h3>
                    <div class="props-card-loc">{{ $booking->property->city }}{{ $booking->property->country ? ', '.$booking->property->country : '' }}</div>
                </div>
            </div>

            <section class="props-detail-section">
                <h2>Trip details</h2>
                <ul style="list-style:none; padding:0; margin:0;">
                    <li style="display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid var(--line);">
                        <span class="props-card-loc">Check-in</span>
                        <span style="font-weight:600;">{{ $booking->check_in_date->format('D, M j, Y') }}</span>
                    </li>
                    <li style="display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid var(--line);">
                        <span class="props-card-loc">Check-out</span>
                        <span style="font-weight:600;">{{ $booking->check_out_date->format('D, M j, Y') }}</span>
                    </li>
                    <li style="display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid var(--line);">
                        <span class="props-card-loc">Nights</span>
                        <span style="font-weight:600;">{{ $booking->nights }}</span>
                    </li>
                    <li style="display:flex; justify-content:space-between; padding:10px 0;">
                        <span class="props-card-loc">Guests</span>
                        <span style="font-weight:600;">{{ $booking->guests }}</span>
                    </li>
                </ul>
            </section>
        </div>

        <aside>
            <div class="props-book-card">
                <h3 style="font-family:'Fraunces',serif; font-weight:600; margin:0 0 16px;">Payment summary</h3>

                <ul style="list-style:none; padding:0; margin:0; font-size:14px;">
                    <li style="display:flex; justify-content:space-between; padding:8px 0;">
                        <span class="props-card-loc">${{ number_format($booking->nightly_rate_cents/100) }} × {{ $booking->nights }} {{ Str::plural('night', $booking->nights) }}</span>
                        <span>${{ number_format($booking->subtotal_cents/100, 2) }}</span>
                    </li>
                    @if ($booking->cleaning_fee_cents > 0)
                        <li style="display:flex; justify-content:space-between; padding:8px 0;">
                            <span class="props-card-loc">Cleaning fee</span>
                            <span>${{ number_format($booking->cleaning_fee_cents/100, 2) }}</span>
                        </li>
                    @endif
                    <li style="display:flex; justify-content:space-between; padding:8px 0;">
                        <span class="props-card-loc">Service fee</span>
                        <span>${{ number_format($booking->service_fee_cents/100, 2) }}</span>
                    </li>
                    <li style="display:flex; justify-content:space-between; padding:8px 0;">
                        <span class="props-card-loc">Taxes</span>
                        <span>${{ number_format($booking->tax_cents/100, 2) }}</span>
                    </li>
                </ul>

                <div style="display:flex; justify-content:space-between; padding:14px 0 0; margin-top:8px; border-top:2px solid var(--ink); font-weight:600;">
                    <span>Total</span>
                    <span style="font-family:'Fraunces',serif; font-size:20px;">${{ number_format($booking->total_cents/100, 2) }}</span>
                </div>

                @if ($paymentsLive && $booking->status->value === 'pending_payment')
                    <a href="{{ route('bookings.pay', $booking) }}" class="props-book-cta" style="margin-top:18px; display:block; text-align:center;">
                        Pay ${{ number_format($booking->total_cents/100, 2) }}
                    </a>
                    <p class="props-book-fineprint">
                        Powered by NMI. Card details are entered on the next page and never touch our servers.
                    </p>
                @elseif ($paymentsLive && $booking->status->value === 'confirmed')
                    <div style="margin-top:18px; padding:14px; background:#ecfdf5; border:1px solid #a7f3d0; border-radius:10px; font-size:13px; color:#047857;">
                        <strong>Confirmed.</strong> Payment received. We've sent a confirmation email — check-in details follow as the dates approach.
                    </div>
                @elseif (! $paymentsLive)
                    {{-- Card payment is temporarily unavailable. Deliberately says
                         nothing about gateway configuration: this is a customer
                         surface, and which credentials are missing is not their
                         concern (nor safe to advertise). Operators diagnose this
                         from the payment processor console instead. --}}
                    <div style="margin-top:18px; padding:14px; background:#fffbeb; border:1px solid #fde68a; border-radius:10px; font-size:13px; color:#92400e;">
                        <strong>Payment pending.</strong> We aren't able to take card payment for this booking right now.
                        Your reservation is held and nothing has been charged — our team will follow up shortly to complete it.
                    </div>
                @endif

                @if (! $booking->status->isTerminal())
                    <a href="{{ route('bookings.cancel.form', $booking) }}" style="display:block; text-align:center; margin-top:14px; font-size:13px; color:var(--muted);">
                        Cancel this booking
                    </a>
                @endif
            </div>
        </aside>
    </div>

@endsection
