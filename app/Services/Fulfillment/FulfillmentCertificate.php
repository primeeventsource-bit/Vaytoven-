<?php

namespace App\Services\Fulfillment;

use App\Models\AdvertisementFulfillmentRecord as Record;
use App\Models\Property;
use App\Services\Tracking\ActivityRecorder;
use App\Support\Location\LocationComparison;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * ADVERTISEMENT SERVICE FULFILLMENT RECORD, as a PDF.
 *
 * Built only from stored records: the fulfillment rows, the activation in the
 * activity log or advertising period, and the member's own login rows. A field
 * with no record prints "Not recorded". Nothing is estimated, inferred from
 * admin activity, or filled in to make the document look complete.
 *
 * Every event is printed with its OWN IP, location, device and session — the
 * first login's context is never shown against the acceptance.
 */
class FulfillmentCertificate
{
    public const NOT_RECORDED = 'Not recorded';

    public function __construct(
        private readonly AdvertisementFulfillment $fulfillment,
        private readonly MemberIncentive $incentive,
    ) {
    }

    /**
     * Everything the template prints. Public so tests can compare it with the
     * stored rows directly rather than by parsing PDF text.
     *
     * @return array<string, mixed>
     */
    public function payload(Property $property): array
    {
        $state    = $this->fulfillment->state($property->loadMissing('host'));
        $access   = $state['first_access'];
        $accepted = $state['accepted'];
        $member   = $property->host;
        $order    = $state['order'];

        // The headline context is the affirmative act — acceptance — or
        // failing that the first access. Never a login.
        $contextRecord = $accepted ?? $access;
        $context       = $contextRecord ? EvidencePoint::fromRecord($contextRecord, $member) : null;

        // The address as it was on file at the time of that event; the
        // current address only when no event snapshot exists, and labelled.
        $snapshot = $contextRecord?->addressOnFile();
        $useSnapshot = $snapshot && ($snapshot->isKnown() || filled($snapshot->line1));
        $address = $useSnapshot ? $snapshot : $member?->addressOnFile();

        $events = collect([
            $state['first_login'],
            $access ? EvidencePoint::fromRecord($access, $member) : null,
            $accepted ? EvidencePoint::fromRecord($accepted, $member) : null,
        ])->filter()->values();

        $incentive = $member ? $this->incentive->state($member) : null;

        return [
            'certificateNumber'   => $this->number($state),
            'generatedAt'         => et(now(), 'm/d/Y g:i:s A'),
            'status'              => AdvertisementFulfillment::statusLabel($state['status']),

            'memberName'          => $access?->member_name ?? $member?->name ?? self::NOT_RECORDED,
            'memberId'            => $access?->member_number ?? $member?->member_id ?? ($member ? '#'.$member->id : self::NOT_RECORDED),
            'memberEmail'         => $access?->member_email ?? $member?->email ?? self::NOT_RECORDED,
            'addressLines'        => $address && $address->oneLine() ? $address->lines() : [],
            'addressBasis'        => $useSnapshot ? 'as on file at the time of the '.($accepted ? 'acceptance' : 'first access')
                                                  : 'current address on file',

            'advertisementId'     => $property->reference ?? '#'.$property->id,
            'advertisementTitle'  => $property->title,
            'advertisementUrl'    => $access?->advertisement_url ?? route('properties.show', $property),
            'package'             => $access?->package ?? ($order ? $order->package?->label().' · '.$order->weeks.' weeks · order '.$order->reference : self::NOT_RECORDED),

            'activatedAt'         => $this->when($state['activated_at']),
            'firstLoginAt'        => $this->when($state['first_login']?->occurredAt),
            'firstAccessAt'       => $this->when($access?->occurred_at),
            'acceptedAt'          => $this->when($accepted?->occurred_at),

            'acknowledgementVersion' => $accepted?->acknowledgement_version ?? self::NOT_RECORDED,
            'acknowledgementText'    => $accepted?->acknowledgement_text,
            'acknowledgementHash'    => $accepted?->acknowledgement_hash,

            'contextLabel'        => $context?->label,
            'ipAddress'           => $context?->ipAddress ?? self::NOT_RECORDED,
            'location'            => $context?->approximateLocation() ?? self::NOT_RECORDED,
            'comparison'          => $context ? $context->comparisonLabel() : self::NOT_RECORDED,
            'comparisonSentence'  => $context ? LocationComparison::sentenceFor($context->comparisonStatus()) : null,
            'comparisonReason'    => $context?->comparison['reason'] ?? null,
            'device'              => $context ? ActivityRecorder::deviceLabel($context->deviceType) : self::NOT_RECORDED,
            'browser'             => $context?->browser ?? self::NOT_RECORDED,
            'operatingSystem'     => $context?->platform ?? self::NOT_RECORDED,
            'sessionId'           => $context?->sessionId ?? self::NOT_RECORDED,

            'events'              => $events,
            'records'             => collect([$access, $accepted])->filter()->values(),
            'corrections'         => $state['corrections'],

            'incentive'           => $incentive,
            'disclosure'          => LocationComparison::DISCLOSURE,
        ];
    }

    public function render(Property $property): string
    {
        return Pdf::loadView('certificates.advertisement-fulfillment', $this->payload($property))
            ->setPaper('letter', 'portrait')
            ->output();
    }

    public function filename(Property $property): string
    {
        return 'vaytoven-fulfillment-'.($property->reference ?? $property->id).'-'.now()->format('Ymd').'.pdf';
    }

    /**
     * Derived from the evidence it certifies: the same records always give the
     * same number, and any new record (an acceptance, a correction) gives a
     * new one.
     */
    private function number(array $state): string
    {
        $basis = collect([$state['first_access'], $state['accepted']])
            ->merge($state['corrections'])
            ->filter()
            ->map(fn (Record $r) => $r->record_hash)
            ->prepend($state['scope_key'])
            ->prepend((string) $state['activated_at']?->toIso8601String())
            ->implode('|');

        return 'VAY-FC-'.strtoupper(substr(hash('sha256', $basis), 0, 12));
    }

    private function when($at): string
    {
        return $at ? et($at, 'm/d/Y g:i:s A') : self::NOT_RECORDED;
    }
}
