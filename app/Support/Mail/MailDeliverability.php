<?php

namespace App\Support\Mail;

use Illuminate\Support\Facades\Config;

/**
 * Can this application actually put a message in someone's inbox?
 *
 * Laravel's `log` and `array` transports accept every message and report
 * success. Mail::to(...)->send(...) returns normally, Password::sendResetLink()
 * returns RESET_LINK_SENT, and the user is told their reset link has been sent.
 * Nothing was sent. The failure is completely silent and looks identical to
 * success from every layer above it.
 *
 * That is how this site ran: MAIL_MAILER was unset on every Laravel Cloud
 * environment, so it fell through to the 'log' default and password reset was
 * impossible for every account while the UI claimed otherwise.
 *
 * Locally that behaviour is desirable — you do not want a dev machine emailing
 * real users. So the question is not "is the mailer log?" but "is the mailer
 * log somewhere it has no business being?".
 */
class MailDeliverability
{
    /** Transports that swallow mail instead of delivering it. */
    private const SINKS = ['log', 'array'];

    /**
     * True when mail can reach a real recipient.
     *
     * A sink transport is fine in local/testing and an outage anywhere else.
     */
    public static function isDeliverable(): bool
    {
        if (! self::isSink()) {
            return self::smtpLooksConfigured();
        }

        return app()->environment('local', 'testing');
    }

    /** True when the configured transport discards mail. */
    public static function isSink(): bool
    {
        return in_array(Config::get('mail.default'), self::SINKS, true);
    }

    /**
     * A short reason, safe to log and to show an operator. Never shown to an
     * end user — it names infrastructure.
     */
    public static function reason(): ?string
    {
        if (self::isDeliverable()) {
            return null;
        }

        if (self::isSink()) {
            return sprintf(
                'MAIL_MAILER is "%s" in the %s environment, which discards mail instead of sending it.',
                Config::get('mail.default'),
                app()->environment(),
            );
        }

        return 'The smtp mailer has no host or credentials configured.';
    }

    /**
     * Guards against the other silent failure: MAIL_MAILER=smtp with nothing
     * behind it. That points at 127.0.0.1 by config default, where there is no
     * mail server, so every send throws a transport exception at runtime —
     * later, and in a place nobody is watching.
     */
    private static function smtpLooksConfigured(): bool
    {
        if (Config::get('mail.default') !== 'smtp') {
            return true;    // a provider transport configured elsewhere
        }

        if (Config::get('mail.mailers.smtp.url')) {
            return true;
        }

        $host = (string) Config::get('mail.mailers.smtp.host');

        if ($host === '' || $host === '127.0.0.1' || $host === 'localhost') {
            return false;
        }

        // Public relays universally require authentication. A host with no
        // credentials is either a local relay or a half-finished config.
        return (bool) Config::get('mail.mailers.smtp.username');
    }
}
