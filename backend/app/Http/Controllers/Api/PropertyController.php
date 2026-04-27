<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PropertyResource;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PropertyController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Property::query()
            ->published()
            ->with(['images', 'amenities', 'host'])
            ->withCount('reviews');

        // Location filter
        if ($city = $request->string('city')->toString()) {
            $query->where('city', 'ilike', "%$city%");
        }
        if ($country = $request->string('country')->toString()) {
            $query->where('country_code', strtoupper($country));
        }

        // Date availability
        if ($request->filled(['check_in', 'check_out'])) {
            $query->availableBetween($request->date('check_in'), $request->date('check_out'));
        }

        // Guest count
        if ($guests = (int) $request->integer('guests')) {
            $query->where('max_guests', '>=', $guests);
        }

        // Property type
        if ($type = $request->string('type')->toString()) {
            $query->where('property_type', $type);
        }

        // Price range (in dollars from client, stored as cents)
        if ($min = $request->integer('price_min')) {
            $query->where('base_price_cents', '>=', $min * 100);
        }
        if ($max = $request->integer('price_max')) {
            $query->where('base_price_cents', '<=', $max * 100);
        }

        // Instant book / superhost flags
        if ($request->boolean('instant_book')) {
            $query->where('instant_book', true);
        }
        if ($request->boolean('superhost')) {
            $query->whereHas('host', fn ($q) => $q->where('is_superhost', true));
        }

        // Amenities — caller passes slugs as comma-list or array
        if ($amenities = $request->input('amenities')) {
            $slugs = is_array($amenities) ? $amenities : explode(',', $amenities);
            foreach ($slugs as $slug) {
                $query->whereHas('amenities', fn ($q) => $q->where('slug', $slug));
            }
        }

        // Sort
        match ($request->string('sort')->toString()) {
            'price_asc'  => $query->orderBy('base_price_cents'),
            'price_desc' => $query->orderByDesc('base_price_cents'),
            'rating'     => $query->orderByDesc('rating_avg'),
            default      => $query->orderByDesc('rating_avg')->orderByDesc('booking_count'),
        };

        return PropertyResource::collection(
            $query->paginate(perPage: min((int) $request->integer('per_page', 20), 50))
        );
    }

    public function show(string $slug): PropertyResource
    {
        $property = Property::query()
            ->published()
            ->where('slug', $slug)
            ->with(['images', 'amenities', 'host', 'reviews.reviewer'])
            ->withCount('reviews')
            ->firstOrFail();

        return new PropertyResource($property);
    }

    /**
     * Live price quote for a date range — used by the listing detail page sidebar.
     */
    public function quote(string $slug, Request $request): JsonResponse
    {
        $property = Property::published()->where('slug', $slug)->firstOrFail();

        $request->validate([
            'check_in'  => 'required|date|after:today',
            'check_out' => 'required|date|after:check_in',
            'guests'    => 'required|integer|min:1',
        ]);

        $quote = app(\App\Services\PricingService::class)->quote(
            $property,
            \Carbon\CarbonImmutable::parse($request->date('check_in')),
            \Carbon\CarbonImmutable::parse($request->date('check_out')),
            $request->integer('guests'),
        );

        return response()->json($quote);
    }
}
