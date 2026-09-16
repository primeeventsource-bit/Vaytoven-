<?php

namespace App\Console\Commands;

use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Services\Fulfillment\AdvertisementFulfillment;
use Illuminate\Console\Command;

/**
 * Writes first-access records from historical evidence. Dry run unless --commit.
 *
 * Default: only where the activity log shows the owning member viewing their
 * own listing after activation (AdvertisementFulfillment::historicalFirstAccess).
 *
 * --from-logins: for advertisements still without access, record access from
 * the member's first login after activation — Vaytoven's decision of
 * 2026-09-16. Those records say so (source backfill:login).
 *
 * Never writes acceptances, never uses admin activity, never touches existing
 * rows.
 */
class BackfillAdvertisementAccess extends Command
{
    protected $signature = 'vaytoven:backfill-advertisement-access
        {--commit : write the records}
        {--from-logins : record access from the first member login after activation}';

    protected $description = 'Backfill member first-access records from historical evidence';

    public function handle(AdvertisementFulfillment $fulfillment): int
    {
        $rows = [];
        $written = 0;
        $skipped = ['no recorded activation' => 0, 'no login after activation' => 0, 'already accessed' => 0, 'staff or no owner' => 0];
        $fromLogins = (bool) $this->option('from-logins');

        Property::query()
            ->where('status', PropertyStatus::Active->value)
            ->with('host')
            ->orderBy('id')
            ->each(function (Property $property) use ($fulfillment, $fromLogins, &$rows, &$written, &$skipped) {
                if (! $property->host || $property->host->isStaff()) {
                    $skipped['staff or no owner']++;

                    return;
                }

                $state = $fulfillment->state($property);

                if ($state['first_access']) {
                    $skipped['already accessed']++;

                    return;
                }

                if ($fromLogins) {
                    if (! $state['activated_at']) {
                        $skipped['no recorded activation']++;

                        return;
                    }

                    $login = $fulfillment->firstLoginAfter($property->host, $state['activated_at']);

                    if (! $login) {
                        $skipped['no login after activation']++;

                        return;
                    }

                    $rows[] = [$property->reference, $property->host->email, 'login', (string) $login->occurred_at, $login->ip_address];

                    if ($this->option('commit') && $fulfillment->backfillFirstAccessFromLogin($property)) {
                        $written++;
                    }

                    return;
                }

                $view = $fulfillment->historicalFirstAccess($property);

                if (! $view) {
                    return;
                }

                $rows[] = [$property->reference, $property->host->email, 'view #'.$view->id, (string) $view->occurred_at, $view->ip_address];

                if ($this->option('commit') && $fulfillment->backfillFirstAccess($property, $view)) {
                    $written++;
                }
            });

        $this->table(['Advertisement', 'Member', 'Basis', 'Occurred', 'IP'], $rows);
        $this->line(count($rows).' advertisement(s) qualify.');

        if ($fromLogins) {
            foreach ($skipped as $reason => $count) {
                $this->line("  skipped ({$reason}): {$count}");
            }
        }

        $this->option('commit')
            ? $this->info("Wrote {$written} first-access record(s).")
            : $this->warn('Dry run — nothing written. Re-run with --commit.');

        return self::SUCCESS;
    }
}
