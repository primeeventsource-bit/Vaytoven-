@extends('dashboard.layout')

@section('eyebrow', 'Member')
@section('title', 'Welcome back, ' . $me->name)

@section('content')

    @if ($myEnquiry)
        <section class="vyt-section">
            <div class="vyt-card">
                <div class="vyt-card-header">
                    <h3>Your Managed Listing Program enquiry</h3>
                    <span class="vyt-section-meta">Reference {{ $myEnquiry->reference ?? '—' }}</span>
                </div>
                <div class="vyt-card-body">
                    <ul class="vyt-kv">
                        <li><span class="k">Club</span><span class="v">{{ $myEnquiry->club }}</span></li>
                        <li><span class="k">Property</span><span class="v">{{ $myEnquiry->property }}</span></li>
                        <li><span class="k">Annual points</span><span class="v">{{ number_format((int) $myEnquiry->points) }}</span></li>
                        <li><span class="k">Status</span><span class="v"><span class="vyt-pill">{{ $myEnquiry->status }}</span></span></li>
                        <li><span class="k">Submitted</span><span class="v">{{ $myEnquiry->created_at?->diffForHumans() }}</span></li>
                    </ul>
                </div>
            </div>
        </section>
    @endif

    @include('partials.listing-analytics', [
        'listings'        => $listings,
        'totalViews30d'   => $totalViews30d,
        'totalViews7d'    => $totalViews7d,
        'uniqueCountries' => $uniqueCountries,
        'perListingStats' => $perListingStats,
        'pinPoints'       => $pinPoints,
    ])

@endsection
