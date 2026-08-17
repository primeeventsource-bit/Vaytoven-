{{-- The same analytics panel hosts see for their own listings, scoped to this
     member. Rendered from ListingAnalytics so the numbers here and on the
     member's own dashboard cannot disagree. --}}
@include('partials.listing-analytics', [
    'listings'        => $properties,
    'totalViews30d'   => $totalViews30d,
    'totalViews7d'    => $totalViews7d,
    'uniqueCountries' => $uniqueCountries,
    'perListingStats' => $perListingStats,
    'pinPoints'       => $pinPoints,
    'pinsByListing'   => $pinsByListing,
    'mapboxToken'     => $mapboxToken,
    'mapboxStyle'     => $mapboxStyle,
])
