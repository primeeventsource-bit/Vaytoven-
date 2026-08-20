<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Property
 */
class PropertyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'listing_source' => $this->listing_source,
            'host_id' => $this->host_id,
            'location' => [
                'latitude' => (float) $this->latitude,
                'longitude' => (float) $this->longitude,
                'city' => $this->city,
                'region' => $this->region,
                'country' => $this->country,
            ],
            'capacity' => $this->capacity,
            'bedrooms' => $this->bedrooms,
            'beds' => $this->beds,
            'bathrooms' => (float) $this->bathrooms,
            // Money is integer cents (NFR / hard rule).
            'price_cents' => $this->price_cents,
            'cleaning_fee_cents' => $this->cleaning_fee_cents,
            'cancellation_policy' => $this->cancellation_policy?->value,
            'minimum_nights' => $this->minimum_nights,
            'status' => $this->status?->value,
            'amenities' => $this->whenLoaded(
                'amenities',
                fn () => $this->amenities->map(fn ($a) => [
                    'slug' => $a->slug,
                    'label' => $a->label,
                    'category' => $a->category,
                ])
            ),
            'photos' => $this->whenLoaded(
                'photos',
                fn () => $this->photos->map(fn ($p) => [
                    'url' => $p->url,
                    'sort_order' => $p->sort_order,
                    'caption' => $p->caption,
                ])
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
