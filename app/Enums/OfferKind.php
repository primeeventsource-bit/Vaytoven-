<?php

namespace App\Enums;

/**
 * Whether a buyer submission names a price.
 *
 * Both kinds are tracked identically and both expire on the same 24-hour
 * clock — the distinction only changes whether an amount is displayed.
 */
enum OfferKind: string
{
    /** Carries offer_amount_cents. */
    case Offer = 'offer';

    /** A question with no price attached. */
    case Inquiry = 'inquiry';

    public function label(): string
    {
        return $this === self::Offer ? 'Offer' : 'Inquiry';
    }
}
