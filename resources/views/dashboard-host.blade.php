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
        'mapboxToken'     => $mapboxToken,
        'mapboxStyle'     => $mapboxStyle,
    ])

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
