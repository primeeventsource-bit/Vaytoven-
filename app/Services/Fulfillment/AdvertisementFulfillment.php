<?php

namespace App\Services\Fulfillment;

use App\Enums\ActivityType;
use App\Enums\AdvertisingPeriodStatus;
use App\Enums\MemberServiceOrderStatus;
use App\Enums\PropertyStatus;
use App\Models\AdvertisementFulfillmentRecord as Record;
use App\Models\AdvertisingPeriod;
use App\Models\LoginSession;
use App\Models\MemberServiceOrder;
use App\Models\Property;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Services\GeoIp\GeoIpService;
use App\Services\Tracking\ActivityRecorder;
use App\Support\Location\LocationComparison;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Records, and reads back, the three facts that prove advertising was delivered:
 *
 *   Vaytoven activated the advertisement → the member accessed it → the member
 *   affirmatively accepted it.
 *
 * WHO is decided by authentication alone. A record is written only for the
 * signed-in, non-staff account that owns the listing. An admin opening,
 * previewing or activating a member's advertisement is never member access —
 * that is the confusion this exists to end.
 *
 * The "advertisement" is the listing, scoped to its paid advertising period
 * when one exists. On vaytoven.com listings are published directly by staff
 * and most have no period, so a period cannot be required; when one exists, a
 * new paid period is a new service and gets its own access and acceptance.
 */
class AdvertisementFulfillment
{
    public const STATUS_NOT_ACCESSED = 'not_accessed';
    public const STATUS_ACCESSED     = 'accessed';
    public const STATUS_ACCEPTED     = 'accepted';

    public function __construct(
        private readonly ActivityRecorder $activity,
        private readonly GeoIpService $geoIp,
    ) {
    }

    // --- who counts --------------------------------------------------------

    /** The member this advertisement belongs to, if the viewer is that member. */
    public function isOwningMember(?User $user, Property $property): bool
    {
        return $user !== null
            && ! $user->isStaff()
            && (int) $property->host_id === (int) $user->id;
    }

    /** Access and acceptance only mean something once the ad is actually running. */
    public function isRunning(Property $property): bool
    {
        return $property->status === PropertyStatus::Active;
    }

    // --- writing -----------------------------------------------------------

    /**
     * Note that the member opened their advertisement.
     *
     * The first time per advertisement writes the permanent first-access record;
     * every time writes an activity-log row. Never throws: a member must not be
     * locked out of their own advertisement because evidence failed to write.
     */
    public function recordAccess(Request $request, Property $property, User $member, bool $review = false): ?Record
    {
        if (! $this->isOwningMember($member, $property) || ! $this->isRunning($property)) {
            return null;
        }

        try {
            $scope    = $this->scope($property);
            $existing = $this->find(Record::EVENT_FIRST_ACCESS, $scope);

            if ($existing) {
                // The review page is the member looking at their advertisement
                // and its acceptance; the public page is them seeing it as a
                // traveler would. Both are recorded, under their own names.
                $this->activity->record(
                    $review ? ActivityType::MemberAdvertisementReviewed : ActivityType::MemberAdvertisementAccessed,
                    $request,
                    subjectType: 'property',
                    subjectReference: $property->reference,
                    result: 'successful',
                    metadata: ['first_access_record' => $existing->record_uuid],
                    actor: $member,
                );

                return $existing;
            }

            $event = $this->activity->record(
                ActivityType::MemberAdvertisementFirstAccessed,
                $request,
                subjectType: 'property',
                subjectReference: $property->reference,
                result: 'completed',
                metadata: ['scope' => $scope['key']],
                actor: $member,
            );

            $activatedAt = $this->activatedAt($property, $scope['period']);
            $firstLogin  = $this->firstLoginAfter($member, $activatedAt);

            return $this->insert([
                'event'             => Record::EVENT_FIRST_ACCESS,
                'dedupe_key'        => Record::EVENT_FIRST_ACCESS.':'.$scope['key'],
                'tracking_event_id' => $event?->id,
            ] + $this->firstLoginColumns($firstLogin)
              + $this->advertisementColumns($property, $scope['period'], $activatedAt)
              + $this->memberColumns($member)
              + $this->requestColumns($request, $member));
        } catch (Throwable $e) {
            Log::error('Advertisement first-access evidence was not recorded.', [
                'property_id' => $property->id,
                'user_id'     => $member->id,
                'exception'   => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * The member's affirmative acceptance.
     *
     * Refused unless this member has already accessed this advertisement —
     * acceptance of something never opened is not acceptance. Idempotent: a
     * second press returns the original record and changes nothing.
     *
     * @throws RuntimeException with a message fit to show the member
     */
    public function accept(Request $request, Property $property, User $member): Record
    {
        if (! $this->isOwningMember($member, $property)) {
            throw new RuntimeException('Only the member who owns this advertisement can accept it.');
        }

        if (! $this->isRunning($property)) {
            throw new RuntimeException('This advertisement is not active, so it cannot be accepted yet.');
        }

        $scope = $this->scope($property);

        if ($accepted = $this->find(Record::EVENT_ACCEPTED, $scope)) {
            return $accepted;
        }

        $firstAccess = $this->find(Record::EVENT_FIRST_ACCESS, $scope);

        if (! $firstAccess) {
            throw new RuntimeException('Please open and review your advertisement before accepting it.');
        }

        $event = $this->activity->record(
            ActivityType::MemberAdvertisementAccepted,
            $request,
            subjectType: 'property',
            subjectReference: $property->reference,
            result: 'completed',
            metadata: [
                'scope'                   => $scope['key'],
                'acknowledgement_version' => AdvertisementAcknowledgement::VERSION,
                'acknowledgement_hash'    => AdvertisementAcknowledgement::hash(),
            ],
            actor: $member,
        );

        return $this->insert([
            'event'                   => Record::EVENT_ACCEPTED,
            'dedupe_key'              => Record::EVENT_ACCEPTED.':'.$scope['key'],
            'acknowledgement_version' => AdvertisementAcknowledgement::VERSION,
            'acknowledgement_text'    => AdvertisementAcknowledgement::TEXT,
            'acknowledgement_hash'    => AdvertisementAcknowledgement::hash(),
            'tracking_event_id'       => $event?->id,
            'metadata'                => ['first_access_record' => $firstAccess->record_uuid],
        ] + $this->advertisementColumns($property, $scope['period'], $firstAccess->advertisement_activated_at)
          + $this->memberColumns($member)
          + $this->requestColumns($request, $member));
    }

    /**
     * A staff note against an existing record. The original is never altered;
     * anyone reading the evidence sees both, in order.
     */
    public function correct(Record $original, string $note, User $staff, ?Request $request = null): Record
    {
        if (trim($note) === '') {
            throw new RuntimeException('A correction needs an explanation.');
        }

        $request ??= request();

        $event = $this->activity->record(
            ActivityType::FulfillmentCorrected,
            $request,
            subjectType: 'property',
            subjectReference: $original->property_reference,
            result: 'completed',
            metadata: ['corrects' => $original->record_uuid],
            actor: $staff,
        );

        return $this->insert([
            'event'                      => Record::EVENT_CORRECTION,
            'dedupe_key'                 => null,
            'corrects_record_id'         => $original->id,
            'correction_note'            => $note,
            'recorded_by_user_id'        => $staff->id,
            'tracking_event_id'          => $event?->id,
            'property_id'                => $original->property_id,
            'property_reference'         => $original->property_reference,
            'advertisement_url'          => $original->advertisement_url,
            'advertising_period_id'      => $original->advertising_period_id,
            'member_service_order_id'    => $original->member_service_order_id,
            'order_reference'            => $original->order_reference,
            'package'                    => $original->package,
            'advertisement_activated_at' => $original->advertisement_activated_at,
            'user_id'                    => $original->user_id,
            'member_number'              => $original->member_number,
            'member_name'                => $original->member_name,
            'member_email'               => $original->member_email,
            'member_role'                => $original->member_role,
        ] + $this->requestColumns($request));
    }

    /**
     * The earliest reliable historical evidence of first access, if any.
     *
     * Reliable means all three of: an authenticated actor who is the listing's
     * owner and not staff; the advertisement itself (subject_reference); and a
     * server timestamp at or after a recorded activation. Only property.viewed
     * rows qualify — they are written by the server, never by the browser.
     * Admin activity is never evidence of member access, and nothing here can
     * produce an acceptance.
     */
    public function historicalFirstAccess(Property $property): ?TrackingEvent
    {
        $member = $property->host;

        if (! $member || $member->isStaff() || ! $property->reference) {
            return null;
        }

        $activatedAt = $this->activatedAt($property, $this->scope($property)['period']);

        if (! $activatedAt) {
            return null;
        }

        return TrackingEvent::query()
            ->where('event_type', ActivityType::PropertyViewed->value)
            ->where('subject_reference', $property->reference)
            ->where('actor_user_id', $member->id)
            ->where(fn ($q) => $q->whereNull('actor_role')->orWhereNotIn('actor_role', TrackingEvent::STAFF_ROLES))
            ->where('occurred_at', '>=', $activatedAt)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->first();
    }

    /** Writes the backfilled first-access record. Returns null if one already exists. */
    public function backfillFirstAccess(Property $property, TrackingEvent $view): ?Record
    {
        $scope = $this->scope($property);

        if ($this->find(Record::EVENT_FIRST_ACCESS, $scope)) {
            return null;
        }

        $member      = $property->host;
        $activatedAt = $this->activatedAt($property, $scope['period']);
        $firstLogin  = $this->firstLoginAfter($member, $activatedAt);

        return $this->insert([
            'event'                    => Record::EVENT_FIRST_ACCESS,
            'dedupe_key'               => Record::EVENT_FIRST_ACCESS.':'.$scope['key'],
            'source'                   => Record::SOURCE_BACKFILL,
            'source_tracking_event_id' => $view->id,
            'tracking_event_id'        => $view->id,
            'ip_address'               => $view->ip_address,
            'country'                  => $view->country,
            'region'                   => $view->region,
            'city'                     => $view->city,
            'latitude'                 => $view->latitude,
            'longitude'                => $view->longitude,
            // No address snapshot and no stored comparison: what was on file
            // on that date is not known, and is not reconstructed. Displays
            // compare against the current address and label it as such.
            'device_type'              => $view->device_type ?? ActivityRecorder::deviceType($view->user_agent),
            'browser'                  => $view->browser ?? ActivityRecorder::browser($view->user_agent),
            'platform'                 => $view->platform ?? ActivityRecorder::platform($view->user_agent),
            'session_id'               => $view->session_id,
            'user_agent'               => $view->user_agent,
            'referrer_host'            => $view->referrer_host,
            'path'                     => $view->path,
            'occurred_at'              => $view->occurred_at,
            'metadata'                 => ['basis' => 'Owner-authenticated property.viewed after recorded activation'],
        ] + $this->firstLoginColumns($firstLogin)
          + $this->advertisementColumns($property, $scope['period'], $activatedAt)
          + $this->identityColumns($member));
    }

    // --- reading -----------------------------------------------------------

    /**
     * Everything known about one advertisement's fulfillment.
     *
     * @return array{
     *     property: Property, scope_key: string, period: ?AdvertisingPeriod,
     *     order: ?MemberServiceOrder, activated_at: ?Carbon, first_login: ?EvidencePoint,
     *     first_access: ?Record, accepted: ?Record, corrections: Collection, status: string
     * }
     */
    public function state(Property $property): array
    {
        $scope       = $this->scope($property);
        $firstAccess = $this->find(Record::EVENT_FIRST_ACCESS, $scope);
        $accepted    = $this->find(Record::EVENT_ACCEPTED, $scope);

        // Snapshotted values win: they describe the moment of access. The
        // live lookups only fill in for an advertisement nobody has opened,
        // and they read stored records — nothing here is inferred.
        $activatedAt = $firstAccess?->advertisement_activated_at
            ?? $this->activatedAt($property, $scope['period']);

        $member = $property->host;

        // Each login is read from its OWN row, with its own IP and device.
        $firstLogin = match (true) {
            (bool) $firstAccess?->first_login_event_id   => TrackingEvent::with('actor')->find($firstAccess->first_login_event_id),
            (bool) $firstAccess?->first_login_session_id => LoginSession::find($firstAccess->first_login_session_id),
            $member !== null                             => $this->firstLoginAfter($member, $activatedAt),
            default                                      => null,
        };

        $ids = collect([$firstAccess?->id, $accepted?->id])->filter();

        return [
            'property'     => $property,
            'scope_key'    => $scope['key'],
            'period'       => $scope['period'],
            'order'        => $this->order($property, $scope['period']),
            'activated_at' => $activatedAt,
            'first_login'  => $firstLogin ? $this->evidence($firstLogin, $member, 'First member login after activation') : null,
            'first_access' => $firstAccess,
            'accepted'     => $accepted,
            'corrections'  => $ids->isEmpty() ? collect() : Record::query()
                ->where('event', Record::EVENT_CORRECTION)
                ->whereIn('corrects_record_id', $ids)
                ->orderBy('id')
                ->get(),
            'status'       => $accepted ? self::STATUS_ACCEPTED
                : ($firstAccess ? self::STATUS_ACCESSED : self::STATUS_NOT_ACCESSED),
        ];
    }

    /**
     * One row per advertisement the member owns that has run or carries evidence.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function forMember(User $member): Collection
    {
        $withRecords = Record::query()->where('user_id', $member->id)->whereNotNull('property_id')->pluck('property_id');

        return Property::query()
            ->where('host_id', $member->id)
            ->where(fn ($q) => $q->where('status', PropertyStatus::Active->value)
                ->orWhereIn('id', $withRecords))
            ->with('host')
            ->orderBy('title')
            ->get()
            ->map(fn (Property $p) => $this->state($p));
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_ACCEPTED => 'ACCEPTED',
            self::STATUS_ACCESSED => 'ACCESSED',
            default               => 'NOT ACCESSED',
        };
    }

    // --- internals -----------------------------------------------------------

    /** @return array{key: string, period: ?AdvertisingPeriod} */
    public function scope(Property $property): array
    {
        $period = AdvertisingPeriod::query()
            ->where('property_id', $property->id)
            ->whereNotNull('activated_at')
            ->whereNotIn('status', [AdvertisingPeriodStatus::Cancelled->value, AdvertisingPeriodStatus::Pending->value])
            ->orderByDesc('activated_at')
            ->orderByDesc('id')
            ->first();

        return [
            'key'    => $period ? 'period:'.$period->id : 'property:'.$property->id,
            'period' => $period,
        ];
    }

    private function find(string $event, array $scope): ?Record
    {
        return Record::query()
            ->where('dedupe_key', $event.':'.$scope['key'])
            ->first();
    }

    /**
     * When Vaytoven activated it, from stored records only: the paid period's
     * activation, or failing that the most recent activation recorded in the
     * activity log. Null when neither exists — never guessed.
     */
    public function activatedAt(Property $property, ?AdvertisingPeriod $period): ?Carbon
    {
        if ($period?->activated_at) {
            return $period->activated_at;
        }

        if (! $property->reference) {
            return null;
        }

        $at = TrackingEvent::query()
            ->where('event_type', ActivityType::AdvertisementActivated->value)
            ->where('subject_reference', $property->reference)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->value('occurred_at');

        return $at ? Carbon::parse($at) : null;
    }

    /**
     * The member's first sign-in at or after activation.
     *
     * The activity-log row is preferred — it carries the visit's session id.
     * login_sessions is the fallback for sign-ins older than that row type,
     * and a login_sessions row that is genuinely earlier wins, so a gap in
     * one log cannot move "first".
     */
    public function firstLoginAfter(User $member, ?Carbon $activatedAt): TrackingEvent|LoginSession|null
    {
        if (! $activatedAt) {
            return null;
        }

        $event = TrackingEvent::query()
            ->with('actor')
            ->where('event_type', ActivityType::LoginSucceeded->value)
            ->where('actor_user_id', $member->id)
            ->where('occurred_at', '>=', $activatedAt)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->first();

        $session = LoginSession::query()
            ->where('user_id', $member->id)
            ->where('auth_event', 'login')
            ->where('occurred_at', '>=', $activatedAt)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->first();

        if ($event && $session) {
            // One sign-in writes both within the same second.
            return $session->occurred_at->lt($event->occurred_at->copy()->subSeconds(5)) ? $session : $event;
        }

        return $event ?? $session;
    }

    public function evidence(TrackingEvent|LoginSession $login, ?User $member, string $label): EvidencePoint
    {
        return $login instanceof TrackingEvent
            ? EvidencePoint::fromTrackingEvent($login, $member, $label)
            : EvidencePoint::fromLoginSession($login, $member ?? User::findOrFail($login->user_id), $label);
    }

    private function firstLoginColumns(TrackingEvent|LoginSession|null $login): array
    {
        return [
            'first_login_at'         => $login?->occurred_at,
            'first_login_event_id'   => $login instanceof TrackingEvent ? $login->id : null,
            'first_login_session_id' => $login instanceof LoginSession ? $login->id : null,
        ];
    }

    /** The paid order behind the advertisement, most specific link first. */
    public function order(Property $property, ?AdvertisingPeriod $period): ?MemberServiceOrder
    {
        if ($period?->order) {
            return $period->order;
        }

        if ($property->member_service_order_id) {
            $order = MemberServiceOrder::find($property->member_service_order_id);

            if ($order) {
                return $order;
            }
        }

        $email = $property->host?->email;

        return $email ? MemberServiceOrder::query()
            ->where('email', $email)
            ->where('status', MemberServiceOrderStatus::Paid->value)
            ->orderByDesc('paid_at')
            ->first() : null;
    }

    public function insert(array $attributes): Record
    {
        $now = now();

        try {
            return Record::create($attributes + ['occurred_at' => $now] + ['recorded_at' => $now]);
        } catch (UniqueConstraintViolationException) {
            // Two requests raced for the same "first". The database kept one;
            // that one is the record.
            return Record::query()->where('dedupe_key', $attributes['dedupe_key'])->firstOrFail();
        }
    }

    private function advertisementColumns(Property $property, ?AdvertisingPeriod $period, ?Carbon $activatedAt): array
    {
        $order = $this->order($property, $period);

        return [
            'property_id'                => $property->id,
            'property_reference'         => $property->reference,
            'advertisement_url'          => route('properties.show', $property),
            'advertising_period_id'      => $period?->id,
            'member_service_order_id'    => $order?->id,
            'order_reference'            => $order?->reference,
            'package'                    => $order?->package?->label(),
            'advertisement_activated_at' => $activatedAt,
        ];
    }

    /** Identity plus the address on file as it stands at the moment of this event. */
    public function memberColumns(User $member): array
    {
        return $this->identityColumns($member) + [
            'address_line1'       => $member->address_line1,
            'address_line2'       => $member->address_line2,
            'address_city'        => $member->address_city,
            'address_state'       => $member->address_state,
            'address_postal_code' => $member->address_postal_code,
            'address_country'     => $member->address_country,
        ];
    }

    private function identityColumns(User $member): array
    {
        return [
            'user_id'       => $member->id,
            'member_number' => $member->member_id,
            'member_name'   => $member->name,
            'member_email'  => $member->email,
            'member_role'   => $member->role?->value,
        ];
    }

    /**
     * This request's own network context, and — for a member — how its
     * approximate IP location compares with their address on file.
     */
    public function requestColumns(?Request $request, ?User $member = null): array
    {
        if (! $request) {
            return [];
        }

        $ip = $request->ip();
        $ua = $request->userAgent();
        $geo = null;

        try {
            $geo = $ip ? $this->geoIp->lookup($ip) : null;
        } catch (Throwable) {
            // Location is a nice-to-have on evidence that is otherwise complete.
        }

        $comparison = $member && ! $member->isStaff()
            ? LocationComparison::compare($member->addressOnFile(), $geo?->city, $geo?->region, $geo?->country, $geo?->latitude, $geo?->longitude)
            : null;

        return [
            'ip_address'    => $ip,
            'country'       => $geo?->country,
            'region'        => $geo?->region,
            'city'          => $geo?->city,
            'latitude'      => $geo?->latitude,
            'longitude'     => $geo?->longitude,
            'location_comparison'        => $comparison?->status,
            'location_distance_miles'    => $comparison?->distanceMiles,
            'location_comparison_reason' => $comparison ? mb_substr($comparison->reason, 0, 160) : null,
            'device_type'   => ActivityRecorder::deviceType($ua),
            'browser'       => ActivityRecorder::browser($ua),
            'platform'      => ActivityRecorder::platform($ua),
            'session_id'    => $this->activity->sessionId($request),
            'user_agent'    => $ua ? mb_substr($ua, 0, 512) : null,
            'referrer_host' => ActivityRecorder::referrerHost($request->headers->get('referer')),
            'path'          => mb_substr($request->path(), 0, 512),
        ];
    }
}
