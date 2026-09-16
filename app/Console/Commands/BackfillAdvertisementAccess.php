<?php

namespace App\Console\Commands;

use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Services\Fulfillment\AdvertisementFulfillment;
use Illuminate\Console\Command;

/**
 * Writes first-access records ONLY where the existing activity log reliably
 * identifies the member, the advertisement and the time. See
 * AdvertisementFulfillment::historicalFirstAccess() for the exact rule.
 *
 * Never writes acceptances, never uses admin activity, never touches existing
 * rows. Dry run unless --commit.
 */
class BackfillAdvertisementAccess extends Command
{
    protected $signature = 'vaytoven:backfill-advertisement-access {--commit : write the records}';

    protected $description = 'Backfill member first-access records from reliable historical evidence only';

    public function handle(AdvertisementFulfillment $fulfillment): int
    {
        $rows = [];
        $written = 0;

        Property::query()
            ->where('status', PropertyStatus::Active->value)
            ->with('host')
            ->orderBy('id')
            ->each(function (Property $property) use ($fulfillment, &$rows, &$written) {
                $state = $fulfillment->state($property);

                if ($state['first_access']) {
                    return;
                }

                $view = $fulfillment->historicalFirstAccess($property);

                if (! $view) {
                    return;
                }

                $rows[] = [$property->reference, $property->host?->email, $view->id, (string) $view->occurred_at, $view->ip_address];

                if ($this->option('commit') && $fulfillment->backfillFirstAccess($property, $view)) {
                    $written++;
                }
            });

        $this->table(['Advertisement', 'Member', 'Evidence event', 'Occurred', 'IP'], $rows);
        $this->line(count($rows).' advertisement(s) have reliable historical first-access evidence.');

        $this->option('commit')
            ? $this->info("Wrote {$written} backfilled first-access record(s).")
            : $this->warn('Dry run — nothing written. Re-run with --commit.');

        return self::SUCCESS;
    }
}
