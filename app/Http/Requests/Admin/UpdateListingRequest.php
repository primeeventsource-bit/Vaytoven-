<?php

namespace App\Http\Requests\Admin;

use App\Enums\PropertyStatus;
use Foundation\Http\FormRequest as Unused;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Everything the listing builder saves.
 *
 * Almost every field is nullable on purpose. A listing is assembled over
 * several sittings — basics today, photos when the member sends them, dates
 * once the club confirms — and a builder that refuses to save until every box
 * is filled means staff keep the work in a notepad instead. What a listing
 * needs before it may go LIVE is a separate question, answered when the status
 * changes rather than on every save.
 */
class UpdateListingRequest extends FormRequest
{
    /** Kinds a member would recognise, not exchange-network taxonomy. */
    public const KINDS = [
        'resort'      => 'Resort',
        'condo'       => 'Condo',
        'villa'       => 'Villa',
        'hotel_suite' => 'Hotel-style suite',
        'house'       => 'House',
        'cabin'       => 'Cabin',
        'apartment'   => 'Apartment',
        'other'       => 'Other',
    ];

    public const VIEW_TYPES = [
        'ocean', 'beachfront', 'pool', 'garden', 'city', 'mountain',
        'golf course', 'lake', 'river', 'courtyard', 'no specific view',
    ];

    public const LOCATION_PRECISION = [
        'exact'       => 'Exact pin — show the property where it is',
        'approximate' => 'Approximate — round the pin to about a kilometre',
        'city_only'   => 'City only — publish no pin at all',
    ];

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('properties.edit') ?? false;
    }

    public function rules(): array
    {
        return [
            // --- basics ----------------------------------------------------
            'title'               => ['required', 'string', 'max:200'],
            'resort_name'         => ['nullable', 'string', 'max:160'],
            'property_kind'       => ['nullable', Rule::in(array_keys(self::KINDS))],
            'status'              => ['required', Rule::enum(PropertyStatus::class)],
            'host_id'             => ['required', 'exists:users,id'],
            'position_in_package' => ['nullable', 'integer', 'min:1', 'max:20'],

            // --- location ---------------------------------------------------
            'address_line'       => ['nullable', 'string', 'max:255'],
            'city'               => ['nullable', 'string', 'max:120'],
            'region'             => ['nullable', 'string', 'max:120'],
            'country'            => ['nullable', 'string', 'max:2'],
            'postal_code'        => ['nullable', 'string', 'max:20'],
            'latitude'           => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'          => ['nullable', 'numeric', 'between:-180,180'],
            'location_precision' => ['required', Rule::in(array_keys(self::LOCATION_PRECISION))],

            // --- details ----------------------------------------------------
            'bedrooms'            => ['nullable', 'integer', 'min:0', 'max:50'],
            'bathrooms'           => ['nullable', 'numeric', 'min:0', 'max:50'],
            'beds'                => ['nullable', 'integer', 'min:0', 'max:100'],
            'capacity'            => ['nullable', 'integer', 'min:1', 'max:100'],
            'bed_configuration'   => ['nullable', 'string', 'max:500'],
            'square_feet'         => ['nullable', 'integer', 'min:1', 'max:100000'],
            'floor_unit'          => ['nullable', 'string', 'max:60'],
            'check_in_day'        => ['nullable', 'string', 'max:16'],
            'check_in_time'       => ['nullable', 'string', 'max:16'],
            'check_out_time'      => ['nullable', 'string', 'max:16'],
            'minimum_nights'      => ['nullable', 'integer', 'min:1', 'max:365'],
            'unit_size_type'      => ['nullable', 'string', 'max:80'],
            'view_type'           => ['nullable', 'string', 'max:60'],
            'accessibility_notes' => ['nullable', 'string', 'max:2000'],
            'pet_policy'          => ['nullable', 'string', 'max:160'],
            'smoking_policy'      => ['nullable', 'string', 'max:160'],
            'parking_info'        => ['nullable', 'string', 'max:255'],

            // --- description -------------------------------------------------
            'headline'          => ['nullable', 'string', 'max:180'],
            'short_description' => ['nullable', 'string', 'max:400'],
            'description'       => ['nullable', 'string', 'max:20000'],
            'highlights'        => ['nullable', 'array', 'max:12'],
            'highlights.*'      => ['nullable', 'string', 'max:120'],

            // --- amenities ----------------------------------------------------
            'amenities'         => ['nullable', 'array'],
            'amenities.*'       => ['integer', 'exists:amenities,id'],
            'custom_amenity'    => ['nullable', 'string', 'max:80'],

            // --- offer settings ------------------------------------------------
            'allow_offers'             => ['boolean'],
            'allow_inquiries'          => ['boolean'],
            'display_suggested_amount' => ['boolean'],
            'require_guest_count'      => ['boolean'],
            'require_message'          => ['boolean'],
            'minimum_offer_dollars'    => ['nullable', 'numeric', 'min:0', 'max:1000000'],
        ];
    }

    /**
     * Unchecked boxes are absent from a form post, not false. Without this
     * every save would leave the existing value alone and a setting could
     * never be turned OFF — the switch would appear to work in one direction.
     */
    protected function prepareForValidation(): void
    {
        foreach ([
            'allow_offers', 'allow_inquiries', 'display_suggested_amount',
            'require_guest_count', 'require_message',
        ] as $flag) {
            $this->merge([$flag => $this->boolean($flag)]);
        }
    }

    public function messages(): array
    {
        return [
            'country.max'         => 'Use the two-letter country code, e.g. US.',
            'highlights.max'      => 'Twelve highlights is already more than anyone reads.',
            'location_precision.required' => 'Choose how precisely the map may show this property.',
        ];
    }
}
