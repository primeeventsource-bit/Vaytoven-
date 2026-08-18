<?php

namespace App\Enums;

/**
 * What is happening with one advertised week.
 *
 * Four states rather than a boolean, because "not bookable" hides a
 * distinction that matters to everyone involved: a week with an offer under
 * consideration is still live advertising the member may accept, while a week
 * the member has taken back is not advertising at all.
 */
enum AvailabilityWeekStatus: string
{
    case Available    = 'available';
    case OfferPending = 'offer_pending';
    case Unavailable  = 'unavailable';
    case Closed       = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Available    => 'Available',
            self::OfferPending => 'Offer pending',
            self::Unavailable  => 'Unavailable',
            self::Closed       => 'Closed',
        };
    }

    /** Shown to the public, and open to offers. */
    public function isPublic(): bool
    {
        return $this === self::Available || $this === self::OfferPending;
    }

    /** Accepting new offers. A week already under offer is not. */
    public function acceptsOffers(): bool
    {
        return $this === self::Available;
    }

    public function description(): string
    {
        return match ($this) {
            self::Available    => 'Advertised and open to offers.',
            self::OfferPending => 'Shown publicly, but an offer is already under consideration.',
            self::Unavailable  => 'Withdrawn by the member. Not advertised.',
            self::Closed       => 'Closed by staff — outside the service period, or not eligible.',
        };
    }
}
