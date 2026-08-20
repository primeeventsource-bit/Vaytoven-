<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the office that a member has signed in for the first time.
 *
 * That first sign-in is the moment fulfilment completes: the member paid for
 * advertising, an account was issued, and they have now used it. Until it
 * happens nobody knows whether the credentials ever reached the person, and
 * the gap between "we sent it" and "they got in" is where a member goes quiet
 * and later disputes the charge.
 *
 * It carries where and how the sign-in happened, because "the account was used"
 * and "the account was used from the member's own phone in their own city" are
 * different facts, and the second is the one worth having if the charge is
 * questioned later.
 *
 * INTERNAL ONLY. This goes to the office address and nowhere else — no
 * recipient, cc or bcc for the member. A "you signed in" email tells the member
 * nothing they do not already know, and sending one for an event they just
 * performed reads as a security alert about themselves.
 */
class MemberFirstSignIn extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $context  where and how — see
     *                                         TrackAuthEvents::signInContext()
     */
    public function __construct(
        public readonly User $member,
        public readonly array $context = [],
    ) {
    }

    public function envelope(): Envelope
    {
        // The office address, resolved once so it cannot drift from the rest
        // of the application.
        $office = (string) config('mail.office_address', config('mail.from.address'));

        return new Envelope(
            to: [$office],
            subject: 'First sign-in — '.$this->member->name.' ('.$this->member->email.')',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.members.first-sign-in',
        );
    }
}
