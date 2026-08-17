<?php

namespace App\Mail;

use App\Models\Property;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Tells an owner that staff created a listing under their name.
 *
 * Sent whether or not the account is new. A listing appearing under someone's
 * name without telling them is how a platform ends up advertising a property
 * the owner never agreed to advertise — and the first they hear of it is an
 * inquiry from a stranger.
 */
class ListingCreatedForOwner extends Mailable
{
    public function __construct(
        public readonly Property $property,
        public readonly User $owner,
        /** Only set when the account was created as part of this. */
        public readonly ?string $temporaryPassword = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your Vaytoven listing: {$this->property->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.listings.created-for-owner',
            with: [
                'property'          => $this->property,
                'owner'             => $this->owner,
                'temporaryPassword' => $this->temporaryPassword,
            ],
        );
    }
}
