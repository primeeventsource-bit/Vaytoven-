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
 * INTERNAL ONLY. This goes to the office address and nowhere else — no
 * recipient, cc or bcc for the member. A "you signed in" email tells the member
 * nothing they do not already know, and sending one for an event they just
 * performed reads as a security alert about themselves.
 */
class MemberFirstSignIn extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $member,
        public readonly ?string $ipAddress = null,
        public readonly ?string $signedInAt = null,
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
