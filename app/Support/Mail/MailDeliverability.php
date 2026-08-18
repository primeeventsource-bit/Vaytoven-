<?php

namespace App\Support\Mail;

use Illuminate\Support\Facades\Cache;
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
 *
 * CONFIGURED IS NOT DELIVERING. This class originally answered only the
 * configuration question, and that produced a worse failure than the one it was
 * written to catch: with a host, a username and a password all present but the
 * password rejected by the provider, /health reported mail as "ok" while every
 * send died with SMTP 535. A false green is more dangerous than a known-bad
 * state, because nobody investigates green.
 *
 * So there are now two separate questions, and callers should be explicit about
 * which one they are asking:
 *
 *   isDeliverable() — is it plausibly configured? Cheap, no network.
 *   isVerified()    — has a message ever actually left this environment?
 *
 * Verification is recorded from Laravel's MessageSent event, so any real send
 * counts and nothing has to be instrumented per call site. It is deliberately
 * not a live SMTP handshake: opening a session on every health poll would be
 * its own outage.
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
     * Cache key holding the timestamp of the last message the provider
     * accepted.
     *
     * Cache rather than a table: this is an observation about the running
     * environment, not a business record. A cache flush resets it to
     * "unverified", which fails in the safe direction — it under-claims rather
     * than over-claims, and the next successful send restores it.
     */
    private const VERIFIED_KEY = 'mail.last_successful_send_at';

    /** Called from the MessageSent listener. Any real send counts. */
    public static function recordSuccessfulSend(): void
    {
        if (self::isSink()) {
            return;     // a log transport "succeeding" proves nothing
        }

        Cache::forever(self::VERIFIED_KEY, now()->toIso8601String());
    }

    public static function lastSuccessfulSendAt(): ?string
    {
        $at = Cache::get(self::VERIFIED_KEY);

        return is_string($at) ? $at : null;
    }

    /**
     * Has a message actually been accepted by the provider from this
     * environment?
     *
     * A sink transport counts as verified in local and testing, for the same
     * reason isDeliverable() does: there is nothing to prove there.
     */
    public static function isVerified(): bool
    {
        if (self::isSink()) {
            return app()->environment('local', 'testing');
        }

        return self::lastSuccessfulSendAt() !== null;
    }

    /**
     * Why mail is configured but unproven. Distinct from reason(), which
     * explains why it is not configured at all.
     */
    public static function unverifiedReason(): ?string
    {
        if (self::isVerified()) {
            return null;
        }

        if (! self::isDeliverable()) {
            return self::reason();
        }

        return sprintf(
            'The %s transport is configured but no message has ever been accepted from the %s '
            .'environment, so delivery is unproven. Run `php artisan mail:test <address>` — a '
            .'rejected password looks exactly like this and reports 535 there.',
            Config::get('mail.default'),
            app()->environment(),
        );
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

        // Public relays universally require authentication, and they require
        // BOTH halves of it. A username with no password is the most likely
        // half-finished state — someone sets MAIL_USERNAME while waiting on an
        // API key — and it fails at send time with an auth error rather than
        // at configuration time, which is exactly the delay this class exists
        // to remove.
        return (bool) Config::get('mail.mailers.smtp.username')
            && (bool) Config::get('mail.mailers.smtp.password');
    }
}
