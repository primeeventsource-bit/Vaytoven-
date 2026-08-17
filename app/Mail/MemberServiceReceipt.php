<?php

namespace App\Mail;

use App\Models\MemberServiceOrder;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class MemberServiceReceipt extends Mailable
{
    public function __construct(public readonly MemberServiceOrder $order)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Receipt — Vaytoven Member Services {$this->order->reference}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.member-services.receipt',
            with: ['order' => $this->order],
        );
    }
}
