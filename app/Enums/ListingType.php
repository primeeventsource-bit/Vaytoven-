<?php

namespace App\Enums;

/**
 * What the advertisement is offering: a stay, or the property itself.
 *
 * Separate from PropertyStatus, which is where the listing is in the workflow
 * (Draft, Pending Review, Active, Paused, Archived). A listing has both at
 * once — a For Sale listing still has to be drafted, reviewed and published —
 * and collapsing them would leave no way to keep a sale listing unpublished.
 */
enum ListingType: string
{
    case Rent = 'rent';
    case Sale = 'sale';

    public function label(): string
    {
        return match ($this) {
            self::Rent => 'For Rent',
            self::Sale => 'For Sale',
        };
    }

    /**
     * How the price is described to a visitor.
     *
     * Every member program stay is seven days and six nights, so a rental
     * price is the price of that stay — not a nightly rate multiplied by
     * something the visitor has to work out. Saying "per night" next to a
     * figure that is actually the whole week is the kind of ambiguity that ends
     * in an argument about what was advertised.
     */
    public function priceCaption(): string
    {
        return match ($this) {
            self::Rent => '7 days / 6 nights',
            self::Sale => 'Asking price',
        };
    }

    public function isForSale(): bool
    {
        return $this === self::Sale;
    }
}
