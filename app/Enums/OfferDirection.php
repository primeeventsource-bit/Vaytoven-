<?php

namespace App\Enums;

/**
 * Which way an offer in `member_offers` flows. See the extension migration
 * for why both directions share one table.
 */
enum OfferDirection: string
{
    /** Vaytoven proposes a booking to the member who owns a managed listing. */
    case ToMember = 'to_member';

    /** A buyer submits an inquiry or offer on a listing; the owner responds. */
    case FromBuyer = 'from_buyer';
}
