@extends('properties.layout')

@section('title', 'Review your booking — ' . $property->title)

@section('content')

    <a href="{{ route('properties.show', $property) }}" class="props-detail-back">← Back to property</a>

    <div class="props-eyebrow" style="margin-top:12px;">Step 1 of 2 · Review</div>
    <h1 class="props-title">Confirm your stay</h1>

    <div class="props-detail-grid">
        <div>
            <div class="props-card" style="display:flex; align-items:center; gap:14px; padding:16px;">
                @if ($property->photos->first())
                    <img src="{{ $property->photos->first()->url }}" alt="{{ $property->title }}" style="width:120px; height:90px; object-fit:cover; border-radius:8px;">
                @endif
                <div>
                    <h3 style="margin:0 0 4px; font-family:'Fraunces',serif; font-weight:600;">{{ $property->title }}</h3>
                    <div class="props-card-loc">{{ $property->city }}{{ $property->country ? ', '.$property->country : '' }}</div>
                </div>
            </div>

            <section class="props-detail-section">
                <h2>Trip details</h2>
                <ul style="list-style:none; padding:0; margin:0;">
                    <li style="display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid var(--line);">
                        <span class="props-card-loc">Check-in</span>
                        <span style="font-weight:600;">{{ $checkIn->format('D, M j, Y') }}</span>
                    </li>
                    <li style="display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid var(--line);">
                        <span class="props-card-loc">Check-out</span>
                        <span style="font-weight:600;">{{ $checkOut->format('D, M j, Y') }}</span>
                    </li>
                    <li style="display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid var(--line);">
                        <span class="props-card-loc">Nights</span>
                        <span style="font-weight:600;">{{ $nights }}</span>
                    </li>
                    <li style="display:flex; justify-content:space-between; padding:10px 0;">
                        <span class="props-card-loc">Guests</span>
                        <span style="font-weight:600;">{{ $guests }}</span>
                    </li>
                </ul>
            </section>

            <section class="props-detail-section">
                <h2>Cancellation policy</h2>
                <p>
                    @switch($property->cancellation_policy?->value)
                        @case('flexible')
                            <strong>Flexible.</strong> Full refund if you cancel at least 24 hours before check-in.
                            @break
                        @case('moderate')
                            <strong>Moderate.</strong> Full refund 5+ days before check-in. 50% between 5 days and 24 hours.
                            @break
                        @case('strict')
                            <strong>Strict.</strong> 50% refund if you cancel at least 7 days before check-in.
                            @break
                    @endswitch
                    <a href="/help/cancellation-{{ $property->cancellation_policy?->value ?? 'moderate' }}" target="_blank" rel="noopener">Read the policy →</a>
                </p>
            </section>
        </div>

        <aside>
            <div class="props-book-card">
                <h3 style="font-family:'Fraunces',serif; font-weight:600; margin:0 0 16px;">Price breakdown</h3>

                <ul style="list-style:none; padding:0; margin:0; font-size:14px;">
                    <li style="display:flex; justify-content:space-between; padding:8px 0;">
                        <span class="props-card-loc">${{ number_format($breakdown['rate_cents']/100) }} × {{ $nights }} {{ Str::plural('night', $nights) }}</span>
                        <span>${{ number_format($breakdown['subtotal_cents']/100, 2) }}</span>
                    </li>
                    @if ($breakdown['cleaning_cents'] > 0)
                        <li style="display:flex; justify-content:space-between; padding:8px 0;">
                            <span class="props-card-loc">Cleaning fee</span>
                            <span>${{ number_format($breakdown['cleaning_cents']/100, 2) }}</span>
                        </li>
                    @endif
                    {{-- Under Single-Fee the host carries the whole fee, so
                         there is no guest service fee to show and the line is
                         omitted entirely rather than displayed as $0.00. --}}
                    @if ($breakdown['service_fee_cents'] > 0)
                        <li style="display:flex; justify-content:space-between; padding:8px 0;">
                            <span class="props-card-loc">Vaytoven Guest Service Fee</span>
                            <span>${{ number_format($breakdown['service_fee_cents']/100, 2) }}</span>
                        </li>
                    @endif
                    <li style="display:flex; justify-content:space-between; padding:8px 0;">
                        <span class="props-card-loc">Taxes</span>
                        <span>${{ number_format($breakdown['tax_cents']/100, 2) }}</span>
                    </li>
                </ul>

                <div style="display:flex; justify-content:space-between; padding:14px 0 0; margin-top:8px; border-top:2px solid var(--ink); font-weight:600;">
                    <span>Total</span>
                    <span style="font-family:'Fraunces',serif; font-size:20px;">${{ number_format($breakdown['total_cents']/100, 2) }}</span>
                </div>

                <form method="POST" action="{{ route('bookings.store', $property) }}" style="margin-top:18px;">
                    @csrf
                    <input type="hidden" name="check_in" value="{{ $checkIn->toDateString() }}">
                    <input type="hidden" name="check_out" value="{{ $checkOut->toDateString() }}">
                    <input type="hidden" name="guests" value="{{ $guests }}">
                    <button type="submit" class="props-book-cta" data-track-audience="traveler" data-track-cta="booking_confirm">
                        Confirm booking
                    </button>
                </form>

                <p class="props-book-fineprint">
                    @if ($breakdown['service_fee_cents'] > 0)
                        This is the complete amount before you authorize payment. The Vaytoven Guest
                        Service Fee is non-refundable; see the cancellation policy for details.
                    @else
                        This is the complete amount before you authorize payment. There is no
                        separate Vaytoven Guest Service Fee on this listing.
                    @endif
                </p>
            </div>
        </aside>
    </div>

    <script src="/vyt-track.js" defer></script>

@endsection
