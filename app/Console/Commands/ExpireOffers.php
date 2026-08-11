<?php

namespace App\Console\Commands;

use App\Services\Offers\OfferService;
use Illuminate\Console\Command;

/**
 * Sweeps offers past their expiry into EXPIRED.
 *
 * Scheduled every minute in routes/console.php: a buyer offer expires at an
 * exact clock time 24 hours after submission, so a slower cadence would leave
 * offers displaying as live past their own deadline. The dashboards also
 * compute expiry read-through (MemberOffer::effectiveStatus), so a missed run
 * never shows a stale ACTIVE — it only delays the stored value catching up.
 */
class ExpireOffers extends Command
{
    protected $signature = 'offers:expire';

    protected $description = 'Mark inquiries and offers past their expiry as EXPIRED';

    public function handle(OfferService $offers): int
    {
        $count = $offers->expireOverdue();

        $this->info($count === 0 ? 'No offers to expire.' : "Expired {$count} offer(s).");

        return self::SUCCESS;
    }
}
