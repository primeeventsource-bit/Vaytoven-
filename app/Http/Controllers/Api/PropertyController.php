<?php

namespace App\Http\Controllers\Api;

use App\Enums\PropertyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SearchPropertiesRequest;
use App\Http\Resources\PropertyResource;
use App\Models\Property;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PropertyController extends Controller
{
    /**
     * Public property search (FR-2.1, FR-2.4). Filters: free-text on title/city,
     * structured: country, min_capacity, max_price_cents, plus bounding-box
     * geo prefilter (lat_min/lat_max/lng_min/lng_max).
     *
     * Only `active`-status properties are visible to the API.
     */
    public function index(SearchPropertiesRequest $request): AnonymousResourceCollection
    {
        $query = Property::query()
            ->where('status', PropertyStatus::Active->value)
            ->with(['amenities', 'photos']);

        if ($q = $request->string('q')->toString()) {
            $query->where(function ($w) use ($q) {
                $w->where('title', 'like', "%{$q}%")
                    ->orWhere('city', 'like', "%{$q}%")
                    ->orWhere('region', 'like', "%{$q}%");
            });
        }

        if ($city = $request->string('city')->toString()) {
            $query->where('city', $city);
        }

        if ($country = $request->string('country')->toString()) {
            $query->where('country', strtoupper($country));
        }

        if ($request->filled('min_capacity')) {
            $query->where('capacity', '>=', (int) $request->integer('min_capacity'));
        }

        if ($request->filled('max_price_cents')) {
            $query->where('price_cents', '<=', (int) $request->integer('max_price_cents'));
        }

        // Bounding-box geo prefilter — exact distance ranking happens in app code
        // post-fetch since SQLite doesn't support spatial functions and we want
        // tests to be portable. Production MySQL would use ST_Distance_Sphere here.
        if ($request->filled(['lat_min', 'lat_max', 'lng_min', 'lng_max'])) {
            $query->whereBetween('latitude', [(float) $request->float('lat_min'), (float) $request->float('lat_max')])
                ->whereBetween('longitude', [(float) $request->float('lng_min'), (float) $request->float('lng_max')]);
        }

        $perPage = (int) $request->integer('per_page', 20);

        return PropertyResource::collection($query->paginate($perPage));
    }

    public function show(Property $property): PropertyResource
    {
        if ($property->status !== PropertyStatus::Active) {
            abort(404);
        }

        return new PropertyResource($property->load(['amenities', 'photos']));
    }
}
