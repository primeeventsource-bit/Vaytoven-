<?php

namespace App\Services\Fulfillment;

use App\Enums\ActivityType;
use App\Models\AdvertisementFulfillmentRecord as Record;
use App\Models\LoginSession;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Services\Tracking\ActivityRecorder;
use App\Support\Location\AddressOnFile;
use App\Support\Location\LocationComparison;
use Carbon\CarbonInterface;

/**
 * One event as evidence: when, who, and the event's OWN network context.
 *
 * Built from whichever stored row holds the event — an activity-log row, a
 * login_sessions row or a fulfillment record — and never assembled from two.
 * That is the rule that stops a login's IP or device being shown against the
 * acceptance that happened later.
 */
final readonly class EvidencePoint
{
    public function __construct(
        public string $key,
        public string $label,
        public CarbonInterface $occurredAt,
        public string $performedBy,          // Member | Admin | Super admin | Member specialist | System
        public ?string $actorName,
        public ?string $advertisementId,
        public ?string $ipAddress,
        public ?string $city,
        public ?string $region,
        public ?string $country,
        public ?float $latitude,
        public ?float $longitude,
        public ?string $deviceType,
        public ?string $browser,
        public ?string $platform,
        public ?string $userAgent,
        public ?string $sessionId,
        public ?AddressOnFile $addressAtEvent,
        /** @var array{status: string, label: string, distance_miles: ?float, reason: string}|null */
        public ?array $comparison,
        public bool $comparisonFromCurrentAddress,
        public ?string $note = null,
        public ?string $source = null,
    ) {
    }

    public static function fromTrackingEvent(TrackingEvent $e, ?User $member = null, ?string $label = null): self
    {
        $meta      = is_array($e->metadata) ? $e->metadata : [];
        $byMember  = $e->actor_user_id !== null && ! $e->isStaffActor();
        $stored    = $meta['location_comparison'] ?? null;

        [$comparison, $fromCurrent] = $byMember
            ? self::comparisonOrCurrent($stored, $member, $e->city, $e->region, $e->country, $e->latitude, $e->longitude)
            : [null, false];

        return new self(
            key: 'te:'.$e->id,
            label: $label ?? $e->activityLabel(),
            occurredAt: $e->occurred_at,
            performedBy: $e->actorClassLabel() ?? 'System',
            actorName: $e->actor?->name ?? ($meta['member']['name'] ?? null),
            advertisementId: $e->subject_type === 'property' ? $e->subject_reference : null,
            ipAddress: $e->ip_address,
            city: $e->city, region: $e->region, country: $e->country,
            latitude: $e->latitude !== null ? (float) $e->latitude : null,
            longitude: $e->longitude !== null ? (float) $e->longitude : null,
            deviceType: $e->device_type ?: ActivityRecorder::deviceType($e->user_agent),
            browser: $e->browser ?: ($e->user_agent ? ActivityRecorder::browser($e->user_agent) : null),
            platform: $e->platform ?: ($e->user_agent ? ActivityRecorder::platform($e->user_agent) : null),
            userAgent: $e->user_agent,
            sessionId: $e->session_id,
            addressAtEvent: isset($meta['address_on_file']) ? AddressOnFile::fromArray($meta['address_on_file']) : null,
            comparison: $comparison,
            comparisonFromCurrentAddress: $fromCurrent,
            note: isset($meta['observed_by']) ? 'IP and device as reported by '.$meta['observed_by'] : null,
        );
    }

    /**
     * A login_sessions row. Its session_id is the framework session token and
     * is deliberately not surfaced.
     */
    public static function fromLoginSession(LoginSession $s, User $member, string $label = 'Member login'): self
    {
        [$comparison, $fromCurrent] = self::comparisonOrCurrent(null, $member, $s->city, $s->region, $s->country, $s->latitude, $s->longitude);

        return new self(
            key: 'ls:'.$s->id,
            label: $label,
            occurredAt: $s->occurred_at,
            performedBy: $member->isStaff() ? 'Admin' : 'Member',
            actorName: $member->name,
            advertisementId: null,
            ipAddress: $s->ip_address,
            city: $s->city, region: $s->region, country: $s->country,
            latitude: $s->latitude !== null ? (float) $s->latitude : null,
            longitude: $s->longitude !== null ? (float) $s->longitude : null,
            deviceType: $s->device_type,
            browser: $s->browser,
            platform: $s->os,
            userAgent: $s->user_agent,
            sessionId: null,
            addressAtEvent: null,
            comparison: $comparison,
            comparisonFromCurrentAddress: $fromCurrent,
        );
    }

    public static function fromRecord(Record $r, ?User $member = null): self
    {
        $stored = $r->location_comparison ? [
            'status'         => $r->location_comparison,
            'label'          => LocationComparison::labelFor($r->location_comparison),
            'distance_miles' => $r->location_distance_miles !== null ? (float) $r->location_distance_miles : null,
            'reason'         => (string) $r->location_comparison_reason,
        ] : null;

        [$comparison, $fromCurrent] = self::comparisonOrCurrent($stored, $member, $r->city, $r->region, $r->country, $r->latitude, $r->longitude);

        return new self(
            key: 'fr:'.$r->id,
            label: match ($r->event) {
                Record::EVENT_ACCEPTED               => 'Advertisement accepted',
                Record::EVENT_CORRECTION             => 'Fulfillment record corrected',
                Record::EVENT_INCENTIVE_PRESENTED    => $r->incentive_name.' presented',
                Record::EVENT_INCENTIVE_ACKNOWLEDGED => $r->incentive_name.' acknowledged',
                Record::EVENT_INCENTIVE_DELIVERED    => $r->incentive_name.' delivery recorded',
                default                              => 'Advertisement first accessed',
            },
            occurredAt: $r->occurred_at,
            performedBy: in_array($r->event, [Record::EVENT_CORRECTION, Record::EVENT_INCENTIVE_DELIVERED], true) ? 'Admin' : 'Member',
            actorName: $r->member_name,
            advertisementId: $r->property_reference,
            ipAddress: $r->ip_address,
            city: $r->city, region: $r->region, country: $r->country,
            latitude: $r->latitude !== null ? (float) $r->latitude : null,
            longitude: $r->longitude !== null ? (float) $r->longitude : null,
            deviceType: $r->device_type,
            browser: $r->browser,
            platform: $r->platform,
            userAgent: $r->user_agent,
            sessionId: $r->session_id,
            addressAtEvent: $r->address_city || $r->address_postal_code || $r->address_line1 ? $r->addressOnFile() : null,
            comparison: $comparison,
            comparisonFromCurrentAddress: $fromCurrent,
            note: match (true) {
                $r->event === Record::EVENT_CORRECTION          => $r->correction_note,
                $r->event === Record::EVENT_INCENTIVE_DELIVERED => trim('Method: '.$r->delivery_method.' · Reference: '.$r->delivery_reference.($r->correction_note ? ' · '.$r->correction_note : '')),
                $r->source !== Record::SOURCE_LIVE              => 'Backfilled from the activity log ('.$r->source.')',
                default                                         => null,
            },
            source: $r->source,
        );
    }

    /**
     * The comparison stored with the event when there is one. Rows written
     * before comparisons existed are compared against the CURRENT address and
     * say so — that is a reading, not a record of what was on file then.
     */
    private static function comparisonOrCurrent(?array $stored, ?User $member, ?string $city, ?string $region, ?string $country, $lat, $lng): array
    {
        if ($stored) {
            return [$stored, false];
        }

        if (! $member) {
            return [null, false];
        }

        return [LocationComparison::compare(
            $member->addressOnFile(), $city, $region, $country,
            $lat !== null ? (float) $lat : null, $lng !== null ? (float) $lng : null,
        )->toArray(), true];
    }

    public function approximateLocation(): ?string
    {
        return collect([$this->city, $this->region, \App\Support\Location\Regions::countryName($this->country)])
            ->filter()->implode(', ') ?: null;
    }

    public function deviceLabel(): string
    {
        return ActivityRecorder::deviceLabel($this->deviceType);
    }

    public function deviceHint(): ?string
    {
        return ActivityRecorder::deviceHint($this->userAgent);
    }

    public function comparisonLabel(): string
    {
        return LocationComparison::labelFor($this->comparison['status'] ?? null);
    }

    public function comparisonStatus(): string
    {
        return $this->comparison['status'] ?? LocationComparison::UNAVAILABLE;
    }

    public function isMemberEvent(): bool
    {
        return $this->performedBy === 'Member';
    }
}
