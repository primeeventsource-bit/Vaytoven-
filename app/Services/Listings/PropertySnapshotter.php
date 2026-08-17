<?php

namespace App\Services\Listings;

use App\Models\Property;
use App\Models\PropertySnapshot;
use App\Models\User;

/**
 * Captures what a listing said at a moment in time.
 *
 * Called when advertising is activated and whenever a MATERIAL field changes —
 * not on every save. A snapshot per touch would bury the two or three that
 * matter under hundreds recording that somebody corrected a typo, and the
 * point of the record is that it can be read.
 */
class PropertySnapshotter
{
    /**
     * Fields whose change alters what a traveler was actually offered.
     *
     * Deliberately excludes timestamps and internal bookkeeping: a snapshot
     * exists to answer "what was advertised", and updated_at changing is not
     * a different advertisement.
     */
    public const MATERIAL_FIELDS = [
        'title', 'description', 'city', 'region', 'country', 'address_line',
        'capacity', 'bedrooms', 'beds', 'bathrooms',
        'base_nightly_cents', 'cleaning_fee_cents', 'minimum_nights',
        'status', 'listing_source',
    ];

    public function capture(Property $property, string $reason, ?User $actor = null): PropertySnapshot
    {
        $content = $this->contentOf($property);

        return PropertySnapshot::create([
            'property_id'         => $property->id,
            'reason'              => $reason,
            'content'             => $content,
            'content_hash'        => PropertySnapshot::hashContent($content),
            'captured_by_user_id' => $actor?->id,
            'captured_at'         => now(),
        ]);
    }

    /**
     * Capture only if a material field actually changed.
     *
     * Returns null when nothing worth recording moved, so callers can hook
     * this to a save without thinking about it.
     */
    public function captureIfMaterialChange(Property $property, ?User $actor = null): ?PropertySnapshot
    {
        $changed = array_intersect(
            array_keys($property->getChanges()),
            self::MATERIAL_FIELDS,
        );

        if ($changed === []) {
            return null;
        }

        return $this->capture($property, PropertySnapshot::REASON_EDITED, $actor);
    }

    /**
     * The listing as published, plus the owner it was published for.
     *
     * Host name and email are copied in rather than referenced: the point of a
     * snapshot is that it still reads correctly years later, and a joined
     * lookup would show today's owner on a historical record.
     */
    private function contentOf(Property $property): array
    {
        return [
            'property_id'        => $property->id,
            'title'              => $property->title,
            'description'        => $property->description,
            'address_line'       => $property->address_line,
            'city'               => $property->city,
            'region'             => $property->region,
            'country'            => $property->country,
            'postal_code'        => $property->postal_code,
            'capacity'           => $property->capacity,
            'bedrooms'           => $property->bedrooms,
            'beds'               => $property->beds,
            'bathrooms'          => (string) $property->bathrooms,
            'base_nightly_cents' => $property->base_nightly_cents,
            'cleaning_fee_cents' => $property->cleaning_fee_cents,
            'minimum_nights'     => $property->minimum_nights,
            'status'             => $property->status->value ?? (string) $property->status,
            'listing_source'     => $property->listing_source,
            'host_id'            => $property->host_id,
            'host_name'          => $property->host?->name,
            'host_email'         => $property->host?->email,
            'public_url'         => route('properties.show', $property, absolute: false),
        ];
    }
}
