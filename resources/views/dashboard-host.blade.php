@extends('dashboard.layout')

@section('eyebrow', 'Host')
@section('title', 'Welcome back, ' . $me->name)

@section('content')

    @include('partials.listing-analytics', [
        'listings'        => $listings,
        'totalViews30d'   => $totalViews30d,
        'totalViews7d'    => $totalViews7d,
        'uniqueCountries' => $uniqueCountries,
        'perListingStats' => $perListingStats,
        'pinPoints'       => $pinPoints,
        'pinsByListing'   => $pinsByListing,
        'mapboxToken'     => $mapboxToken,
        'mapboxStyle'     => $mapboxStyle,
    ])

    <section class="vyt-section">
        <div class="vyt-card">
            <div class="vyt-card-header">
                <h3>Recent bookings</h3>
                <span class="vyt-section-meta">Latest {{ $bookings->count() }} of your listings' reservations</span>
            </div>
            @if ($bookings->isEmpty())
                <div class="vyt-card-empty">
                    No bookings yet. Reservations on your listings will appear here as travelers book.
                </div>
            @else
                {{-- The host's view is about what THEY receive, so the money
                     columns run booking amount → fee structure → host service
                     fee → net, rather than showing only the guest's total. --}}
                <div style="overflow-x:auto;">
                <table class="vyt-table" style="min-width:1040px;">
                    <thead>
                        <tr>
                            <th>Confirmation</th>
                            <th>Guest</th>
                            <th>Listing</th>
                            <th>Dates</th>
                            <th>Status</th>
                            <th style="text-align:right;">Booking amount</th>
                            <th>Fee structure</th>
                            <th style="text-align:right;">Host Service Fee</th>
                            <th style="text-align:right;">Host net</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($bookings as $booking)
                            <tr>
                                <td><a href="{{ route('bookings.show', $booking) }}" class="vyt-mono">{{ $booking->confirmation_code }}</a></td>
                                <td>{{ $booking->traveler?->name ?? '—' }}</td>
                                <td class="vyt-faint">{{ $booking->property?->title ?? '—' }}</td>
                                <td class="vyt-faint">
                                    {{ $booking->check_in_date?->format('M j') }} – {{ $booking->check_out_date?->format('M j, Y') }}
                                    <span style="opacity:0.6;">· {{ $booking->nights }} {{ Str::plural('night', $booking->nights) }}</span>
                                </td>
                                <td><span class="vyt-pill">{{ str_replace('_', ' ', $booking->status->value) }}</span></td>
                                <td style="text-align:right;">${{ number_format($booking->total_cents / 100, 2) }}</td>

                                @if ($booking->hasFeeSnapshot())
                                    <td class="vyt-faint">{{ $booking->fee_structure->label() }}</td>
                                    <td style="text-align:right;">
                                        &minus;${{ number_format($booking->host_fee_cents / 100, 2) }}
                                        <span class="vyt-faint" style="display:block; font-size:11.5px;">{{ $booking->hostFeeRateLabel() }}</span>
                                    </td>
                                    <td style="text-align:right; font-weight:600;">${{ number_format($booking->host_net_cents / 100, 2) }}</td>
                                @else
                                    {{-- Predates the fee structure. Showing a
                                         computed figure here would invent a
                                         number that was never charged. --}}
                                    <td class="vyt-faint" colspan="3" style="font-size:12.5px;">Not recorded for this booking</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
                <p class="vyt-faint" style="font-size:12px; margin:14px 0 0;">
                    Historical records only. Vaytoven advertises listings and does not collect
                    rental payments or pay hosts — figures shown here relate to bookings taken
                    under the previous arrangement, and each keeps the fee rates in force at the
                    time.
                </p>
            @endif
        </div>
    </section>

    <section class="vyt-section">
        <div class="vyt-card">
            <div class="vyt-card-header">
                <h3>My listings</h3>
                <span class="vyt-section-meta">{{ $listings->count() }} total</span>
            </div>
            @if ($listings->isEmpty())
                <div class="vyt-card-empty">
                    No listings yet. Create one from your host control panel to start receiving bookings.
                </div>
            @else
                <table class="vyt-table">
                    <thead>
                        <tr>
                            <th>Listing</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th style="text-align:right;">Nightly</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($listings as $listing)
                            <tr>
                                <td><a href="{{ route('properties.show', $listing) }}">{{ $listing->title }}</a></td>
                                <td class="vyt-faint">
                                    {{ $listing->city }}@if($listing->country), {{ $listing->country }}@endif
                                </td>
                                <td><span class="vyt-pill">{{ $listing->status->value }}</span></td>
                                <td style="text-align:right;">
                                    ${{ number_format($listing->base_nightly_cents / 100, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </section>

@endsection
