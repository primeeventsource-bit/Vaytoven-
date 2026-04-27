<?php

namespace App\Mail;

use App\Models\MembersEnquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Confirmation email sent to the enquirer immediately after they submit
 * the Managed Listing Program form. Queued so the public POST endpoint
 * doesn't block on SMTP latency.
 */
class MembersEnquiryReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly MembersEnquiry $enquiry,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                config('mail.from.address', 'members@vaytoven.com'),
                config('mail.from.name', 'Vaytoven Members')
            ),
            subject: 'We got your enquiry — Vaytoven Members Program',
            tags:    ['members_enquiry', 'transactional'],
            metadata: [
                'enquiry_id' => $this->enquiry->id,
                'program'    => $this->enquiry->program,
                'source'     => $this->enquiry->source,
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view:     'emails.members.enquiry-received',
            text:     'emails.members.enquiry-received-text',
            with: [
                'firstName'        => $this->enquiry->first_name,
                'program'          => $this->enquiry->program,
                'property'         => $this->enquiry->property,
                'bestTimeToCall'   => $this->enquiry->best_time_to_call,
                'submittedAt'      => $this->enquiry->created_at,
                'siteUrl'          => config('app.url'),
                'helpEmail'        => config('mail.help_address', 'members@vaytoven.com'),
            ],
        );
    }
}
