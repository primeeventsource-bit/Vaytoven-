<?php

namespace App\Listeners;

use App\Support\Mail\MailDeliverability;
use Illuminate\Mail\Events\MessageSent;

/**
 * Records that the mail provider accepted a message.
 *
 * Laravel fires MessageSent only after the transport returns without throwing,
 * so this is the cheapest honest evidence that mail leaves this environment.
 * Wiring it to the event rather than to call sites means every send counts —
 * a password reset, an enquiry confirmation, `mail:test` — and no future send
 * has to remember to opt in.
 *
 * "Accepted by the provider" is not "landed in the inbox". SPF, DKIM and DMARC
 * decide that afterwards and nothing observable from here reports on it. What
 * this rules out is the failure that was actually happening: a configured
 * mailer whose credentials the provider rejects, reported as healthy.
 */
class RecordMailWasDelivered
{
    public function handle(MessageSent $event): void
    {
        MailDeliverability::recordSuccessfulSend();
    }
}
