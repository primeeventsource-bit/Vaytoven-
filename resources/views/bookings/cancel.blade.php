@extends('properties.layout')

@section('title', 'Cancel booking ' . $booking->confirmation_code)

@section('content')

    <a href="{{ route('bookings.show', $booking) }}" class="props-detail-back">← Back to booking</a>

    <div class="props-eyebrow" style="margin-top:12px;">Cancellation</div>
    <h1 class="props-title">Cancel this booking?</h1>

    <div class="props-detail-grid">
        <div>
            <div class="props-card" style="display:flex; align-items:center; gap:14px; padding:16px; margin-bottom:24px;">
                <div>
                    <div class="props-card-loc" style="font-size:11px; letter-spacing:.08em; text-transform:uppercase; font-weight:600; margin-bottom:4px;">Confirmation</div>
                    <h3 style="margin:0; font-family:'SFMono-Regular',Consolas,monospace; font-size:16px; color:var(--purple);">{{ $booking->confirmation_code }}</h3>
                </div>
                <div style="margin-left:auto;">
                    <div class="props-card-loc" style="font-size:11px; letter-spacing:.08em; text-transform:uppercase; font-weight:600; margin-bottom:4px;">Total paid</div>
                    <div style="font-family:'Fraunces',serif; font-size:20px; font-weight:600;">${{ number_format($booking->total_cents/100, 2) }}</div>
                </div>
            </div>

            <section class="props-detail-section">
                <h2>Stay details</h2>
                <ul style="list-style:none; padding:0; margin:0;">
                    <li style="display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid var(--line);">
                        <span class="props-card-loc">Property</span>
                        <span style="font-weight:500;">{{ $booking->property->title }}</span>
                    </li>
                    <li style="display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid var(--line);">
                        <span class="props-card-loc">Check-in</span>
                        <span style="font-weight:600;">{{ $booking->check_in_date->format('D, M j, Y') }}</span>
                    </li>
                    <li style="display:flex; justify-content:space-between; padding:10px 0;">
                        <span class="props-card-loc">Check-out</span>
                        <span style="font-weight:600;">{{ $booking->check_out_date->format('D, M j, Y') }}</span>
                    </li>
                </ul>
            </section>

            <section class="props-detail-section">
                <h2>Cancellation policy: {{ ucfirst($booking->cancellation_policy?->value ?? 'moderate') }}</h2>
                <p>
                    @switch($booking->cancellation_policy?->value)
                        @case('flexible')
                            Full refund if you cancel at least 24 hours before check-in.
                            @break
                        @case('moderate')
                            Full refund 5+ days before check-in. 50% refund between 5 days and 24 hours.
                            @break
                        @case('strict')
                            50% refund if you cancel at least 7 days before check-in. No refund within 7 days.
                            @break
                        @case('non_refundable')
                            This booking is non-refundable.
                            @break
                    @endswitch
                    <a href="/help/cancellation-{{ $booking->cancellation_policy?->value ?? 'moderate' }}" target="_blank" rel="noopener">Read the policy →</a>
                </p>
            </section>
        </div>

        <aside>
            <div class="props-book-card">
                <h3 style="font-family:'Fraunces',serif; font-weight:600; margin:0 0 16px;">Refund estimate</h3>

                @php
                    $tonePill = match($breakdown->tier) {
                        'full'    => 'background:#ecfdf5; color:#047857;',
                        'partial' => 'background:#fffbeb; color:#92400e;',
                        default   => 'background:#fef2f2; color:#b91c1c;',
                    };
                @endphp
                <div style="display:inline-block; padding:4px 14px; border-radius:999px; font-size:12px; font-weight:600; letter-spacing:.02em; text-transform:uppercase; {{ $tonePill }} margin-bottom:14px;">
                    {{ $breakdown->tier }} refund
                </div>

                <ul style="list-style:none; padding:0; margin:0; font-size:14px;">
                    <li style="display:flex; justify-content:space-between; padding:8px 0;">
                        <span class="props-card-loc">Nightly subtotal</span>
                        <span>${{ number_format($breakdown->subtotal_refund_cents/100, 2) }}</span>
                    </li>
                    <li style="display:flex; justify-content:space-between; padding:8px 0;">
                        <span class="props-card-loc">Cleaning fee</span>
                        <span>${{ number_format($breakdown->cleaning_refund_cents/100, 2) }}</span>
                    </li>
                    <li style="display:flex; justify-content:space-between; padding:8px 0;">
                        <span class="props-card-loc">Service fee <em style="font-style:normal; font-size:11px; color:var(--muted);">(non-refundable)</em></span>
                        <span style="color:var(--muted);">$0.00</span>
                    </li>
                    <li style="display:flex; justify-content:space-between; padding:8px 0;">
                        <span class="props-card-loc">Tax</span>
                        <span>${{ number_format($breakdown->tax_refund_cents/100, 2) }}</span>
                    </li>
                </ul>

                <div style="display:flex; justify-content:space-between; padding:14px 0 0; margin-top:8px; border-top:2px solid var(--ink); font-weight:600;">
                    <span>Refund total</span>
                    <span style="font-family:'Fraunces',serif; font-size:20px;">${{ number_format($breakdown->total_cents/100, 2) }}</span>
                </div>

                <form method="POST" action="{{ route('bookings.cancel', $booking) }}" style="margin-top:18px;">
                    @csrf
                    <textarea name="reason" placeholder="Optional: tell us why (helps us improve)" maxlength="255" style="width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:8px; font-size:13px; min-height:60px; resize:vertical; outline:none; font-family:'Geist',sans-serif; margin-bottom:14px;"></textarea>

                    <button type="submit" class="props-book-cta" style="background:#fef2f2; color:#b91c1c; border:1px solid #fecaca;" data-track-audience="traveler" data-track-cta="booking_cancel_confirm">
                        Cancel booking
                    </button>
                </form>

                <p class="props-book-fineprint">
                    @if ($breakdown->total_cents > 0)
                        Refunds clear back to the original payment method in 5–10 business days.
                    @else
                        No refund will be issued. The cancellation will still be recorded.
                    @endif
                </p>
            </div>
        </aside>
    </div>

    <script src="/vyt-track.js" defer></script>

@endsection
