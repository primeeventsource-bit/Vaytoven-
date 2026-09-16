<?php

namespace App\Mail;

use App\Models\Property;
use App\Models\User;
use App\Services\Chargeback\ChargebackCertificateService;
use App\Services\Fulfillment\FulfillmentCertificate;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * One client's certificates, filed with the office.
 *
 * INTERNAL ONLY: addressed to the office address and nobody else. The client
 * is never a recipient, cc or bcc.
 *
 * Attaches the Service Usage Confirmation Certificate for the whole life of
 * the account, and one Advertisement Service Fulfillment Record per live
 * advertisement. Both are generated from stored records at send time.
 */
class ClientCertificatesForOffice extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, Property>  $properties
     * @param  array<int, array<string, mixed>>  $summaries  FulfillmentCertificate payloads, same order
     */
    public function __construct(
        public readonly User $client,
        public readonly Collection $properties,
        public readonly array $summaries,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [(string) config('mail.office_address', config('mail.from.address'))],
            subject: 'Client certificates — '.$this->client->name.' ('.$this->client->email.')',
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.members.client-certificates');
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        $client = $this->client;
        $usage  = app(ChargebackCertificateService::class);

        $attachments = [
            Attachment::fromData(
                fn () => $usage->forUser(
                    $client,
                    CarbonImmutable::parse($client->created_at ?? now()->subYear())->startOfDay(),
                    CarbonImmutable::now()->endOfDay(),
                ),
                $usage->filenameFor(userId: $client->id),
            )->withMime('application/pdf'),
        ];

        foreach ($this->properties as $property) {
            $attachments[] = Attachment::fromData(
                fn () => app(FulfillmentCertificate::class)->render($property),
                app(FulfillmentCertificate::class)->filename($property),
            )->withMime('application/pdf');
        }

        return $attachments;
    }
}
