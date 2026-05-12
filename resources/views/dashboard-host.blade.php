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
                <table class="vyt-table">
                    <thead>
                        <tr>
                            <th>Confirmation</th>
                            <th>Guest</th>
                            <th>Listing</th>
                            <th>Dates</th>
                            <th style="text-align:right;">Guests</th>
                            <th>Status</th>
                            <th style="text-align:right;">Total</th>
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
                                <td style="text-align:right;">{{ $booking->guests }}</td>
                                <td><span class="vyt-pill">{{ str_replace('_', ' ', $booking->status->value) }}</span></td>
                                <td style="text-align:right;font-weight:600;">${{ number_format($booking->total_cents / 100, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
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
