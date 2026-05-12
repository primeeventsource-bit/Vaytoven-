<?php

namespace App\Enums;

enum MemberOfferStatus: string
{
    case Pending   = 'pending';
    case Accepted  = 'accepted';
    case Declined  = 'declined';
    case Expired   = 'expired';
    case Withdrawn = 'withdrawn';

    /** Members can act on Pending only. Other statuses are final. */
    public function isActionable(): bool
    {
        return $this === self::Pending;
    }
}
