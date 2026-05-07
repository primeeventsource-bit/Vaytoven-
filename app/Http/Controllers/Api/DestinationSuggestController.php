<?php

namespace App\Http\Controllers\Api;

use App\Enums\PropertyStatus;
use App\Http\Controllers\Controller;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Destination autocomplete for the search bar (Vrbo-style "Where to?").
 *
 * Backed by our own Property catalogue — no Google Places key required.
 * Returns up to 8 suggestions across three buckets:
 *   - cities    — distinct city names that match, with a property count
 *   - countries — distinct ISO-2 country codes that match
 *   - properties — direct property-title matches (max 3, deepest match)
 *
 * The map widget consumes the lat/lng on city + country rows to recentre
 * + zoom, and the property rows to drop a single pin and link straight
 * to the listing.
 */
class DestinationSuggestController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        // Below 2 chars autocomplete is too noisy — nudge the user without
        // a server round trip.
        if (mb_strlen($q) < 2) {
            return response()->json(['query' => $q, 'suggestions' => []]);
        }

        $like = '%'.$q.'%';

        // City bucket — group by city name so duplicates don't clutter the
        // dropdown. Lat/lng come from the cheapest property in each city
        // (good-enough centroid for an 11-zoom map jump).
        $cities = Property::query()
            ->where('status', PropertyStatus::Active->value)
            ->where('city', 'like', $like)
            ->select('city')
            ->selectRaw('COUNT(*) as count, MIN(country) as country, AVG(latitude) as lat, AVG(longitude) as lng')
            ->groupBy('city')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        // Country bucket — same idea on ISO-2 country code.
        $countries = Property::query()
            ->where('status', PropertyStatus::Active->value)
            ->where('country', 'like', $like)
            ->select('country')
            ->selectRaw('COUNT(*) as count, AVG(latitude) as lat, AVG(longitude) as lng')
            ->groupBy('country')
            ->orderByDesc('count')
            ->limit(3)
            ->get();

        // Property bucket — direct title hits, capped tight so they don't
        // crowd out the broader destination matches.
        $properties = Property::query()
            ->where('status', PropertyStatus::Active->value)
            ->where('title', 'like', $like)
            ->select('id', 'title', 'city', 'country', 'latitude', 'longitude')
            ->orderBy('base_nightly_cents')
            ->limit(3)
            ->get();

        $suggestions = [];

        foreach ($cities as $row) {
            $suggestions[] = [
                'type'    => 'city',
                'label'   => $row->city,
                'sublabel' => "{$row->count} ".str($row->count === 1 ? 'property' : 'properties')->toString().($row->country ? " · {$row->country}" : ''),
                'value'   => $row->city,
                'lat'     => (float) $row->lat,
                'lng'     => (float) $row->lng,
                'zoom'    => 11,
            ];
        }

        foreach ($countries as $row) {
            $suggestions[] = [
                'type'     => 'country',
                'label'    => $row->country,
                'sublabel' => "{$row->count} ".str($row->count === 1 ? 'property' : 'properties').' · region',
                'value'    => $row->country,
                'lat'      => (float) $row->lat,
                'lng'      => (float) $row->lng,
                'zoom'     => 5,
            ];
        }

        foreach ($properties as $row) {
            $suggestions[] = [
                'type'     => 'property',
                'label'    => $row->title,
                'sublabel' => "{$row->city}".($row->country ? ", {$row->country}" : ''),
                'value'    => $row->title,
                'property_id' => $row->id,
                'lat'      => (float) $row->latitude,
                'lng'      => (float) $row->longitude,
                'zoom'     => 14,
            ];
        }

        return response()->json([
            'query'       => $q,
            'suggestions' => $suggestions,
        ]);
    }
}
