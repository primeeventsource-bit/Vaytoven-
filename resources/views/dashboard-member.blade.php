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
                        @php($det = $myEnquiry->exchange_detection)
                        @if ($det && ! empty($det['matches']))
                            @php($top = $det['matches'][0])
                            <li>
                                <span class="k">Banking / Exchange Group</span>
                                <span class="v">
                                    {{ $top['exchange_name'] }}
                                    <span class="vyt-faint" style="font-weight:400;font-size:12px;">·&nbsp;{{ $top['confidence'] }}% confidence</span>
                                </span>
                            </li>
                            @if ($det['status'] === 'needs_review')
                                <li>
                                    <span class="k"></span>
                                    <span class="v" style="font-size:13px;color:#92400e;background:#fffbeb;padding:6px 10px;border-radius:8px;font-weight:500;">
                                        Vaytoven found multiple possible exchange groups for this property. Please confirm with your member specialist before publishing.
                                    </span>
                                </li>
                            @endif
                        @endif
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
        'pinsByListing'   => $pinsByListing,
        'mapboxToken'     => $mapboxToken,
        'mapboxStyle'     => $mapboxStyle,
    ])

@endsection
