<?php

namespace App\Enums;

enum MemberOfferStatus: string
{
    /**
     * Awaiting the member's response to an offer Vaytoven sent them
     * (direction = to_member). The original outbound state.
     */
    case Pending = 'pending';

    /**
     * Awaiting the listing owner's response to a buyer submission
     * (direction = from_buyer). Distinct from Pending so each direction reads
     * in its own vocabulary — an outbound offer is pending the member's reply,
     * an inbound offer is live until someone acts or it times out.
     */
    case Active = 'active';

    case Accepted = 'accepted';
    case Declined = 'declined';
    case Expired = 'expired';
    case Withdrawn = 'withdrawn';

    /**
     * Still awaiting a decision, and therefore still eligible to expire.
     * Covers both directions' open states.
     */
    public function isOpen(): bool
    {
        return $this === self::Pending || $this === self::Active;
    }

    /** Open offers are the only ones anyone can accept or decline. */
    public function isActionable(): bool
    {
        return $this->isOpen();
    }

    /** Uppercase label for the offer dashboards. */
    public function label(): string
    {
        return strtoupper($this->value);
    }
}
