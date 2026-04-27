<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->uuid,
            'slug'                => $this->slug,
            'title'               => $this->title,
            'description'         => $this->description,
            'property_type'       => $this->property_type,
            'room_type'           => $this->room_type,

            'max_guests'          => $this->max_guests,
            'bedrooms'            => $this->bedrooms,
            'bathrooms'           => $this->bathrooms,
            'beds'                => $this->beds,

            'location' => [
                'country_code' => $this->country_code,
                'region'       => $this->region,
                'city'         => $this->city,
                'latitude'     => $this->latitude,
                'longitude'    => $this->longitude,
                // Exact address only exposed to confirmed guests in detail view (handle in show())
            ],

            'pricing' => [
                'base_price_cents'      => $this->base_price_cents,
                'cleaning_fee_cents'    => $this->cleaning_fee_cents,
                'extra_guest_fee_cents' => $this->extra_guest_fee_cents,
                'currency'              => $this->currency,
            ],

            'rules' => [
                'min_nights'              => $this->min_nights,
                'max_nights'              => $this->max_nights,
                'check_in_time'           => $this->check_in_time,
                'check_out_time'          => $this->check_out_time,
                'instant_book'            => $this->instant_book,
                'cancellation_policy'     => $this->cancellation_policy,
            ],

            'rating' => [
                'avg'   => $this->rating_avg,
                'count' => $this->rating_count,
            ],

            'images'    => PropertyImageResource::collection($this->whenLoaded('images')),
            'amenities' => $this->whenLoaded('amenities', fn () => $this->amenities->map(fn ($a) => [
                'slug' => $a->slug,
                'name' => $a->name,
                'icon' => $a->icon,
            ])),
            'host'      => new UserResource($this->whenLoaded('host')),

            'reviews_count' => $this->reviews_count ?? null,

            'created_at'   => $this->created_at,
            'published_at' => $this->published_at,
        ];
    }
}
