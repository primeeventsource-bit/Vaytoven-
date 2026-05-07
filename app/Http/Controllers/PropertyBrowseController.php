<?php

namespace App\Http\Controllers;

use App\Enums\PropertyStatus;
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

        if ($request->filled('max_price')) {
            // UI passes whole dollars; storage is integer cents.
            $query->where('base_nightly_cents', '<=', (int) $request->integer('max_price') * 100);
        }

        $properties = $query
            ->orderBy('base_nightly_cents')
            ->paginate(12)
            ->withQueryString();

        return view('properties.index', [
            'properties'  => $properties,
            'q'           => $q ?? '',
            'destination' => $destination ?? '',
            'minCapacity' => $request->integer('min_capacity'),
            'maxPrice'    => $request->integer('max_price'),
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
