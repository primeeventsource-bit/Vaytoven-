<?php

namespace App\Services\Analytics;

use App\Models\PropertyView;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Views, geography and map pins for a set of listings.
 *
 * Lifted out of DashboardController so the admin activity screen can render
 * the same numbers across every listing on the platform. Duplicating the
 * aggregation would have been the faster route and the wrong one: two copies
 * of "views in the last 30 days" drift, and then the host dashboard and the
 * admin dashboard disagree about the same listing.
 */
class ListingAnalytics
{
    /**
     * @param  Collection<int, \App\Models\Property>  $listings
     * @return array<string, mixed> the shape partials/listing-analytics expects
     */
    public function payload(Collection $listings): array
    {
        if ($listings->isEmpty()) {
            return [
                'totalViews30d'   => 0,
                'totalViews7d'    => 0,
                'uniqueCountries' => 0,
                'perListingStats' => [],
                'pinPoints'       => [],
                'pinsByListing'   => [],
                // The map partial reads these unconditionally, so the empty
                // branch has to supply them too — omitting them 500s the
                // dashboard for anyone with no listings yet.
                'mapboxToken'     => $this->publicMapboxTokenOrNull(config('services.mapbox.token')),
                'mapboxStyle'     => config('services.mapbox.style'),
            ];
        }

        $ids      = $listings->pluck('id')->all();
        $cutoff30 = now()->subDays(30);
        $cutoff7  = now()->subDays(7);

        $totalViews30d = PropertyView::whereIn('property_id', $ids)
            ->where('occurred_at', '>=', $cutoff30)->count();

        $totalViews7d = PropertyView::whereIn('property_id', $ids)
            ->where('occurred_at', '>=', $cutoff7)->count();

        $uniqueCountries = PropertyView::whereIn('property_id', $ids)
            ->where('occurred_at', '>=', $cutoff30)
            ->whereNotNull('country')
            ->distinct('country')
            ->count('country');

        // Per-listing aggregates, in two grouped queries rather than three per
        // listing. The admin screen runs this over every listing on the
        // platform, where the per-listing loop the dashboard used would be
        // 3N queries and would fall over on its own success.
        $views30ById = PropertyView::whereIn('property_id', $ids)
            ->where('occurred_at', '>=', $cutoff30)
            ->selectRaw('property_id, count(*) as c')
            ->groupBy('property_id')
            ->pluck('c', 'property_id');

        $views7ById = PropertyView::whereIn('property_id', $ids)
            ->where('occurred_at', '>=', $cutoff7)
            ->selectRaw('property_id, count(*) as c')
            ->groupBy('property_id')
            ->pluck('c', 'property_id');

        $topCityRows = PropertyView::whereIn('property_id', $ids)
            ->where('occurred_at', '>=', $cutoff30)
            ->whereNotNull('city')
            ->selectRaw('property_id, city, country, count(*) as c')
            ->groupBy('property_id', 'city', 'country')
            ->orderByDesc('c')
            ->get()
            ->groupBy('property_id');

        $perListingStats = [];
        foreach ($listings as $listing) {
            $top = $topCityRows->get($listing->id)?->first();

            $perListingStats[$listing->id] = [
                'views_30d'   => (int) ($views30ById[$listing->id] ?? 0),
                'views_7d'    => (int) ($views7ById[$listing->id] ?? 0),
                'top_city'    => $top?->city,
                'top_country' => $top?->country,
            ];
        }

        // Pin aggregation. Group by (lat, lng) rounded so visits from the same
        // metro stack into one pin rather than scattering.
        $aggregate = fn ($q) => $q
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->selectRaw('round(latitude, 1) as lat, round(longitude, 1) as lng, city, country, count(*) as c')
            ->groupBy('lat', 'lng', 'city', 'country')
            ->orderByDesc('c');

        $shape = fn ($r) => [
            'lat'     => (float) $r->lat,
            'lng'     => (float) $r->lng,
            'city'    => $r->city,
            'country' => $r->country,
            'count'   => (int) $r->c,
        ];

        $pinPoints = $aggregate(PropertyView::query()
                ->whereIn('property_id', $ids)
                ->where('occurred_at', '>=', $cutoff30))
            ->limit(200)->get()->map($shape)->all();

        // Per-listing pins for the map drill-down. One grouped query for all
        // listings, then split in PHP — the previous loop issued one query per
        // listing.
        $perListingPinRows = $aggregate(PropertyView::query()
                ->whereIn('property_id', $ids)
                ->where('occurred_at', '>=', $cutoff30)
                ->addSelect('property_id')
                ->groupBy('property_id'))
            ->get()
            ->groupBy('property_id');

        $pinsByListing = [];
        foreach ($listings as $listing) {
            $pinsByListing[(string) $listing->id] = ($perListingPinRows->get($listing->id) ?? collect())
                ->take(100)->map($shape)->values()->all();
        }

        return [
            'totalViews30d'   => $totalViews30d,
            'totalViews7d'    => $totalViews7d,
            'uniqueCountries' => $uniqueCountries,
            'perListingStats' => $perListingStats,
            'pinPoints'       => $pinPoints,
            'pinsByListing'   => $pinsByListing,
            'mapboxToken'     => $this->publicMapboxTokenOrNull(config('services.mapbox.token')),
            'mapboxStyle'     => config('services.mapbox.style'),
        ];
    }

    /**
     * Only a Mapbox PUBLIC token (pk.*) may reach the browser.
     *
     * Secret tokens (sk.*) carry scopes like manage-uploads and create-tokens.
     * If one is configured, fall back to OSM tiles and log it so it can be
     * rotated — shipping it would hand those scopes to every visitor.
     */
    public function publicMapboxTokenOrNull(?string $token): ?string
    {
        if (! $token) {
            return null;
        }

        if (str_starts_with($token, 'pk.')) {
            return $token;
        }

        Log::warning(
            'mapbox: MAPBOX_API does not begin with pk.* — refusing to expose. '
            .'Falling back to OSM tiles. Rotate at https://account.mapbox.com/access-tokens/'
        );

        return null;
    }
}
