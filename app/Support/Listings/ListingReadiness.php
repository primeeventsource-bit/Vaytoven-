<?php

namespace App\Support\Listings;

use App\Enums\AvailabilityWeekStatus;
use App\Models\Property;

/**
 * What a listing still needs before it can be advertised.
 *
 * The point of checking is not tidiness. A member pays once for a 180-day
 * advertising period, and the clock starts when the listing goes live —
 * activating one with no photos and no dates spends part of what they bought
 * on an advertisement nobody can act on. Better to refuse and say why.
 *
 * Deliberately a short list. Every additional requirement is another reason
 * staff cannot publish something a member is waiting for, and a check that
 * blocks more than it protects gets worked around.
 */
class ListingReadiness
{
    /**
     * @return array<int, string> the reasons this listing cannot go live, empty when it can
     */
    public static function blockers(Property $property): array
    {
        $blockers = [];

        if (trim((string) $property->title) === '') {
            $blockers[] = 'It needs a title.';
        }

        // A description or a short description — not both. One of them is what
        // a traveler reads; insisting on both is the kind of rule that gets
        // satisfied by pasting the same text twice.
        if (trim((string) $property->description) === '' && trim((string) $property->short_description) === '') {
            $blockers[] = 'It needs a description, or at least a short one for search cards.';
        }

        if (trim((string) $property->city) === '') {
            $blockers[] = 'It needs a city, or nobody searching a destination will find it.';
        }

        if ($property->photos()->count() === 0) {
            $blockers[] = 'It needs at least one photo. A listing without one is skipped.';
        }

        // Availability is what is actually being advertised. Without it a
        // traveler has nothing to make an offer against, which makes the
        // advertisement decorative.
        $liveWeeks = $property->availabilityWeeks()
            ->whereIn('status', [
                AvailabilityWeekStatus::Available->value,
                AvailabilityWeekStatus::OfferPending->value,
            ])
            ->whereDate('ends_on', '>=', now()->toDateString())
            ->count();

        if ($liveWeeks === 0) {
            $blockers[] = 'It needs at least one upcoming week marked available.';
        }

        return $blockers;
    }

    public static function isReady(Property $property): bool
    {
        return self::blockers($property) === [];
    }
}
