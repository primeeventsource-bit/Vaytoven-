<?php

namespace App\Mail;

use App\Models\MemberEnquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to a prospect once their member enquiry is received (FR-4 verify).
 *
 * Goal: reassure them their request is real and queued, give them a public
 * reference they can quote in any reply, and set a clear expectation of when
 * they'll hear back. Queued so the form post returns instantly.
 */
class MemberEnquiryReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public MemberEnquiry $enquiry)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "We got your request — reference {$this->enquiry->reference}",
            replyTo: [config('mail.from.address')],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.members.enquiry-received',
            with: [
                'firstName' => $this->enquiry->first_name,
                'reference' => $this->enquiry->reference,
                'club'      => $this->enquiry->club,
                'property'  => $this->enquiry->property,
                'points'    => $this->enquiry->points,
            ],
        );
    }
}
