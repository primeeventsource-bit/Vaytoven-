<?php

namespace App\Observers;

use App\Models\Property;
use App\Services\Listings\PropertySnapshotter;

/**
 * Keeps the published history of a listing.
 *
 * Hooked to the model rather than to each controller on purpose: listings are
 * edited from the host dashboard, the admin console, and anywhere else added
 * later. A snapshot that depends on the editor remembering to take one is a
 * snapshot that is missing exactly when it is needed.
 */
class PropertyObserver
{
    public function __construct(private readonly PropertySnapshotter $snapshotter)
    {
    }

    public function updated(Property $property): void
    {
        // Only material changes. captureIfMaterialChange returns null for a
        // touch that does not alter what a traveler was offered.
        $this->snapshotter->captureIfMaterialChange($property, auth()->user());
    }
}
