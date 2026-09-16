<?php

namespace App\Console\Commands;

use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Models\User;
use App\Services\Fulfillment\AdvertisementFulfillment;
use Illuminate\Console\Command;

/**
 * Records staff-attested first access for named members: they reviewed their
 * live advertisement by phone with the staff member who activated it, at the
 * recorded activation. Attributed to that staff member and to --by. Dry run
 * unless --commit.
 */
class AttestAdvertisementAccess extends Command
{
    protected $signature = 'vaytoven:attest-advertisement-access
        {--email=* : member emails}
        {--by= : email of the staff account directing the entry}
        {--commit : write the records}';

    protected $description = 'Record staff-attested advertisement access (member reviewed the live ad by phone with the activating staff member)';

    public function handle(AdvertisementFulfillment $fulfillment): int
    {
        $by = User::query()->where('email', (string) $this->option('by'))->first();

        if (! $by?->isStaff()) {
            $this->error('--by must be the email of a staff account.');

            return self::FAILURE;
        }

        $rows = [];

        foreach ((array) $this->option('email') as $email) {
            $member = User::query()->whereRaw('LOWER(email) = ?', [strtolower($email)])->first();

            if (! $member) {
                $rows[] = [$email, '—', '—', '—', 'no such account'];
                continue;
            }

            $properties = Property::query()->where('host_id', $member->id)
                ->where('status', PropertyStatus::Active->value)->with('host')->get();

            if ($properties->isEmpty()) {
                $rows[] = [$email, '—', '—', '—', 'no active advertisement'];
                continue;
            }

            foreach ($properties as $property) {
                $result = $fulfillment->attestAccessByActivatingStaff($property, $by, (bool) $this->option('commit'));

                $rows[] = [
                    $email,
                    $property->reference,
                    $result['staff']?->name ?? '—',
                    $result['at'] ? (string) $result['at'] : '—',
                    $result['reason'] ?? ($result['record'] ? 'WRITTEN' : 'would write'),
                ];
            }
        }

        $this->table(['Member', 'Advertisement', 'Attesting staff', 'Attested at (activation)', 'Result'], $rows);

        if (! $this->option('commit')) {
            $this->warn('Dry run — nothing written. Re-run with --commit.');
        }

        return self::SUCCESS;
    }
}
