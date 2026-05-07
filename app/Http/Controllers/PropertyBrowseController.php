<?php

namespace App\Http\Controllers;

use App\Enums\PropertyStatus;
use App\Models\Amenity;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public web surface for browsing and viewing properties.
 *
 * Distinct from Api\PropertyController which serves JSON to the SDK and
 * future mobile clients. This controller renders the branded marketing-grade
 * Blade views for visitors arriving from the landing's destination cards
 * and search form.
 *
 * Search filters mirror the API to keep server logic consistent:
 *   - q              free-text on title/city/region
 *   - city, country  exact match
 *   - destination    convenience alias for city (used by destination cards)
 *   - min_capacity, max_price (whole dollars, converted to cents internally)
 *
 * Only `active`-status properties are visible. Inactive/draft listings 404.
 */
class PropertyBrowseController extends Controller
{
    public function index(Request $request): View
    {
        $query = Property::query()
            ->where('status', PropertyStatus::Active->value)
            ->with(['photos' => fn ($q) => $q->orderBy('sort_order')]);

        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(function ($w) use ($q) {
                $w->where('title', 'like', "%{$q}%")
                    ->orWhere('city', 'like', "%{$q}%")
                    ->orWhere('region', 'like', "%{$q}%");
            });
        }

        // 'destination' is the alias the landing destination cards use.
        // It maps to a relaxed city match (handles slugs like 'lake-tahoe').
        if ($destination = trim((string) $request->query('destination', ''))) {
            $needle = str_replace('-', ' ', strtolower($destination));
            $query->whereRaw('LOWER(city) LIKE ?', ["%{$needle}%"]);
        }

        if ($city = trim((string) $request->query('city', ''))) {
            $query->where('city', $city);
        }

        if ($country = trim((string) $request->query('country', ''))) {
            $query->where('country', strtoupper($country));
        }

        // The Vrbo-style search bar submits adults + children + infants
        // separately. Roll them up into a capacity floor (infants typically
        // don't count toward a property's stated capacity).
        $adults = (int) $request->integer('adults', 0);
        $children = (int) $request->integer('children', 0);
        $totalGuests = $adults + $children;
        $minCapacity = max((int) $request->integer('min_capacity', 0), $totalGuests);

        if ($minCapacity > 0) {
            $query->where('capacity', '>=', $minCapacity);
        }

        if ($request->filled('min_price')) {
            $query->where('base_nightly_cents', '>=', (int) $request->integer('min_price') * 100);
        }
        if ($request->filled('max_price')) {
            // UI passes whole dollars; storage is integer cents.
            $query->where('base_nightly_cents', '<=', (int) $request->integer('max_price') * 100);
        }

        // Amenity multi-select. Vrbo-style: every checked amenity must be
        // present on the listing (AND, not OR), so a 'pool + wifi' search
        // only returns properties carrying both.
        $amenitySlugs = collect((array) $request->query('amenities', []))
            ->filter(fn ($s) => is_string($s) && $s !== '')
            ->unique()
            ->values();
        if ($amenitySlugs->isNotEmpty()) {
            $amenityIds = Amenity::query()
                ->whereIn('slug', $amenitySlugs)
                ->pluck('id');

            // If the user asked for amenities but none of them resolve to a
            // known row, force zero results — silently dropping the filter
            // would leak listings the user never asked to see.
            if ($amenityIds->isEmpty()) {
                $query->whereRaw('1=0');
            } else {
                foreach ($amenityIds as $amenityId) {
                    // whereHas per amenity gives AND-of-required semantics —
                    // simplest correct path that survives any pivot row count.
                    $query->whereHas('amenities', fn ($q) => $q->where('amenities.id', $amenityId));
                }
            }
        }

        $properties = $query
            ->orderBy('base_nightly_cents')
            ->paginate(12)
            ->withQueryString();

        // Curated filter chips for the sidebar — small, recognisable set
        // rather than the full 50+ amenity catalogue. Editorial choice.
        $filterAmenities = Amenity::query()
            ->whereIn('slug', [
                'wifi', 'pool', 'hot-tub', 'pets-allowed', 'beachfront',
                'kitchen', 'air-conditioning', 'free-parking', 'fireplace',
                'fast-wifi',
            ])
            ->orderBy('label')
            ->get(['id', 'slug', 'label']);

        return view('properties.index', [
            'properties'      => $properties,
            'q'               => $q ?? '',
            'destination'     => $destination ?? '',
            'minCapacity'     => $request->integer('min_capacity'),
            'minPrice'        => $request->integer('min_price'),
            'maxPrice'        => $request->integer('max_price'),
            'filterAmenities' => $filterAmenities,
            'selectedAmenities' => $amenitySlugs->all(),
        ]);
    }

    public function show(Property $property): View
    {
        if ($property->status !== PropertyStatus::Active) {
            abort(404);
        }

        $property->load(['amenities', 'photos' => fn ($q) => $q->orderBy('sort_order'), 'host:id,name']);

        return view('properties.show', [
            'property' => $property,
        ]);
    }
}
