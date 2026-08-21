<?php

namespace App\Mail;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Attachment;
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

    /**
     * The same record as a PDF, attached.
     *
     * The email announces the event; the attachment is the thing that gets
     * filed. Months later, in a dispute, "when did this member first use the
     * account and from where" is answered by a document somebody can hand over
     * — not by finding the right message in an inbox and hoping it still has
     * its formatting.
     *
     * Built at send time rather than stored: it is derived entirely from data
     * already in the email, so keeping a copy on disk would add a second place
     * for the same facts to live and drift.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn () => Pdf::loadView('docs.first-sign-in-record', [
                    'member'  => $this->member,
                    'context' => $this->context,
                ])->setPaper('letter', 'portrait')->output(),
                $this->filename(),
            )->withMime('application/pdf'),
        ];
    }

    /**
     * Named so it sorts and reads sensibly in a folder of them: who, then when.
     */
    public function filename(): string
    {
        $who = Str::slug(Str::before($this->member->email, '@')) ?: 'member';

        $when = Carbon::now()
            ->setTimezone(config('app.display_timezone', 'America/New_York'))
            ->format('Y-m-d');

        return "first-sign-in-{$who}-{$when}.pdf";
    }
}
