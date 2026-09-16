<?php

namespace App\Console\Commands;

use App\Enums\PropertyStatus;
use App\Mail\ClientCertificatesForOffice;
use App\Models\AdminAuditLog;
use App\Models\Property;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Rules\DeliverableEmailDomain;
use App\Services\AdminAuditLogService;
use App\Services\Admin\DemoDataPurge;
use App\Services\Fulfillment\FulfillmentCertificate;
use App\Support\Mail\MailDeliverability;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Emails each client's certificates to the OFFICE address — never the client.
 *
 * A client is a non-staff account owning at least one active advertisement,
 * excluding demo, test and reserved-domain accounts and anything passed to
 * --exclude. Dry run by default: it lists who would be covered and sends
 * nothing until --send is given.
 *
 * Each send is written to the admin audit log, and a client already sent is
 * skipped on the next run unless --resend is given, so an interrupted run can
 * simply be started again.
 */
class SendClientCertificates extends Command
{
    private const AUDIT_ACTION = 'client.certificates.sent_to_office';

    protected $signature = 'vaytoven:send-client-certificates
        {--send : actually send; without it this is a dry run}
        {--actor= : email of the staff account sending (required with --send)}
        {--only=* : limit to these client emails}
        {--exclude=* : leave out these client emails}
        {--resend : include clients already sent}';

    protected $description = 'Email every client\'s Usage and Fulfillment certificates to the office address (not to clients)';

    public function handle(FulfillmentCertificate $certificates): int
    {
        $office  = (string) config('mail.office_address', config('mail.from.address'));
        $clients = $this->clients();
        $sent    = AdminAuditLog::query()->where('action', self::AUDIT_ACTION)
            ->where('subject_type', User::class)->pluck('subject_id')->map(fn ($id) => (int) $id)->all();

        $this->info("Recipient for every message: {$office}");
        $this->line($clients->count().' client account(s) own an active advertisement.');

        $rows = $clients->map(fn (User $u) => [
            $u->id,
            $u->name,
            $u->email,
            $u->role?->value,
            $u->hostProperties->count(),
            in_array($u->id, $sent, true) ? 'already sent' : '',
        ]);
        $this->table(['ID', 'Name', 'Email', 'Role', 'Active ads', 'Note'], $rows->all());

        if (! $this->option('send')) {
            $this->warn('Dry run — nothing sent. Re-run with --send --actor=<staff email> to email the office.');

            return self::SUCCESS;
        }

        $actor = User::query()->where('email', (string) $this->option('actor'))->first();

        if (! $actor?->isStaff()) {
            $this->error('--actor must be the email of a staff account; it is recorded against every send.');

            return self::FAILURE;
        }

        if (! MailDeliverability::isDeliverable()) {
            $this->error('Mail is not deliverable on this environment: '.MailDeliverability::reason());

            return self::FAILURE;
        }

        $done = 0;
        $failed = 0;

        foreach ($clients as $client) {
            if (! $this->option('resend') && in_array($client->id, $sent, true)) {
                continue;
            }

            try {
                $properties = $client->hostProperties;
                $summaries  = $properties->map(fn (Property $p) => $certificates->payload($p))->all();

                Mail::send(new ClientCertificatesForOffice($client, $properties, $summaries));

                AdminAuditLogService::log(
                    actor:   $actor,
                    action:  self::AUDIT_ACTION,
                    subject: $client,
                    payload: [
                        'to'           => $office,
                        'certificates' => collect($summaries)->pluck('certificateNumber', 'advertisementId')->all(),
                    ],
                );

                $done++;
                $this->line("  sent  {$client->email}");
            } catch (Throwable $e) {
                $failed++;
                $this->error("  FAILED {$client->email}: ".$e->getMessage());
            }

            // Stay well inside the mail provider's rate limit.
            usleep(750_000);
        }

        $this->info("Sent {$done} message(s) to {$office}. Failed: {$failed}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @return Collection<int, User> */
    private function clients(): Collection
    {
        $only    = array_map('strtolower', (array) $this->option('only'));
        $exclude = array_map('strtolower', (array) $this->option('exclude'));

        return User::query()
            ->whereNotIn('role', TrackingEvent::STAFF_ROLES)
            ->whereHas('hostProperties', fn ($q) => $q->where('status', PropertyStatus::Active->value))
            ->with(['hostProperties' => fn ($q) => $q->where('status', PropertyStatus::Active->value)->orderBy('title')])
            ->orderBy('name')
            ->get()
            ->reject(function (User $u) use ($only, $exclude) {
                $email = strtolower($u->email);

                return DeliverableEmailDomain::isReserved($email)
                    || Str::endsWith($email, DemoDataPurge::DEFAULT_SUFFIXES)
                    // Vaytoven's own addresses (demo-host, underwriting.review)
                    // own listings but are not clients.
                    || Str::endsWith($email, '@vaytoven.com')
                    || in_array($email, $exclude, true)
                    || ($only !== [] && ! in_array($email, $only, true));
            })
            ->values();
    }
}
