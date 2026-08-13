<?php

namespace App\Console\Commands;

use App\Support\Mail\MailDeliverability;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Prove mail actually leaves the building.
 *
 * Needed because every layer above the transport reports success regardless:
 * with MAIL_MAILER=log, Mail::send() returns normally and the password reset
 * flow says the link was sent. The only way to know delivery works is to send
 * a real message to a real inbox and go look.
 *
 *   php artisan mail:test you@example.com
 */
class MailTest extends Command
{
    protected $signature = 'mail:test
                            {recipient : Address to send the test message to}
                            {--force : Send even when the transport is a log/array sink}';

    protected $description = 'Send a test email and report exactly which transport handled it';

    public function handle(): int
    {
        $recipient = (string) $this->argument('recipient');

        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->error("Not a valid email address: {$recipient}");

            return self::FAILURE;
        }

        $mailer = Config::get('mail.default');

        $this->line('');
        $this->line('  Transport ....... '.$mailer);
        $this->line('  From ............ '.Config::get('mail.from.address'));
        if ($mailer === 'smtp') {
            $this->line('  Host ............ '.Config::get('mail.mailers.smtp.host')
                .':'.Config::get('mail.mailers.smtp.port'));
            $this->line('  Username ........ '.(Config::get('mail.mailers.smtp.username') ?: '(none)'));
            $this->line('  Scheme .......... '.(Config::get('mail.mailers.smtp.scheme') ?: '(starttls)'));
        }
        $this->line('  Environment ..... '.app()->environment());
        $this->line('');

        if (! MailDeliverability::isDeliverable() && ! $this->option('force')) {
            $this->error('Mail is NOT deliverable: '.MailDeliverability::reason());
            $this->line('');
            $this->line('  Nothing was sent. Re-run with --force to exercise the sink anyway.');

            return self::FAILURE;
        }

        $stamp = now()->toDateTimeString();

        try {
            Mail::raw(
                "Vaytoven mail test.\n\n"
                ."Sent {$stamp} from the {$mailer} transport in the "
                .app()->environment()." environment.\n\n"
                ."If you are reading this in an inbox, password reset works.",
                function ($message) use ($recipient) {
                    $message->to($recipient)->subject('Vaytoven mail test');
                },
            );
        } catch (Throwable $e) {
            $this->error('Send FAILED: '.$e->getMessage());
            $this->line('');
            $this->line('  Common causes: wrong port for the scheme (587 STARTTLS vs 465 smtps),');
            $this->line('  credentials not yet activated, or the From domain not authorised');
            $this->line('  for this provider.');

            return self::FAILURE;
        }

        if (MailDeliverability::isSink()) {
            $this->warn("Handed to the '{$mailer}' transport, which does not deliver. "
                .'Check storage/logs, not the inbox.');

            return self::SUCCESS;
        }

        $this->info("Accepted by the {$mailer} transport for {$recipient}.");
        $this->line('');
        $this->line('  Accepted is not delivered. Check the inbox, and the spam folder —');
        $this->line('  a first send from a new domain lands there without SPF and DKIM.');

        return self::SUCCESS;
    }
}
