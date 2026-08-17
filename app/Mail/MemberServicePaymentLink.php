<?php

namespace App\Mail;

use App\Models\MemberServiceOrder;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class MemberServicePaymentLink extends Mailable
{
    public function __construct(public readonly MemberServiceOrder $order)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your Vaytoven Member Services activation — {$this->order->reference}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.member-services.payment-link',
            with: ['order' => $this->order],
        );
    }
}
