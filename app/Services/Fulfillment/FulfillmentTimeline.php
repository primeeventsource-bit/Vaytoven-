<?php

namespace App\Services\Fulfillment;

use App\Enums\ActivityType;
use App\Models\AdvertisementFulfillmentRecord as Record;
use App\Models\Property;
use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The member's Advertisement Fulfillment Timeline, oldest first.
 *
 * Account created → advertisement activated → member first login → incentive
 * presented → acknowledged → advertisement first accessed → reviewed →
 * accepted — in whatever order they actually happened, each from its own
 * stored row with its own timestamp and network context. Nothing is merged,
 * and nothing is placed in the sequence it "should" have had.
 */
class FulfillmentTimeline
{
    /** Member-side event types gathered from the activity log. */
    private const MEMBER_TYPES = [
        ActivityType::AccountCreated, ActivityType::MemberFirstLogin, ActivityType::LoginSucceeded,
        ActivityType::TermsAccepted, ActivityType::ContractOpened, ActivityType::ContractSigned,
        ActivityType::ProfileUpdated, ActivityType::PasswordReset,
        ActivityType::MemberAdvertisementReviewed, ActivityType::MemberAdvertisementAccessed,
    ];

    /** Staff-side types, about this member's account or listings. */
    private const ADVERTISEMENT_TYPES = [
        ActivityType::AdvertisementCreated, ActivityType::AdvertisementActivated, ActivityType::AdvertisementPaused,
    ];

    /** Logins are frequent; the timeline keeps the most recent this many plus the first after activation. */
    public const LOGIN_LIMIT = 25;

    public function __construct(private readonly AdvertisementFulfillment $fulfillment)
    {
    }

    /** @return Collection<int, EvidencePoint> */
    public function for(User $member): Collection
    {
        $references = Property::query()->where('host_id', $member->id)->whereNotNull('reference')->pluck('reference');

        $events = TrackingEvent::query()
            ->with('actor:id,name,first_name,last_name,email,role')
            ->where(function ($q) use ($member, $references) {
                $q->where(fn ($q) => $q->where('actor_user_id', $member->id)
                    ->whereIn('event_type', array_map(fn ($t) => $t->value, self::MEMBER_TYPES)))
                  // Staff actions ON this member: their account being created,
                  // their address being changed, their incentive delivered.
                  ->orWhere(fn ($q) => $q->where('subject_type', 'user')
                    ->where('subject_reference', (string) $member->id)
                    ->where('actor_user_id', '!=', $member->id)
                    ->whereIn('event_type', [ActivityType::AccountCreated->value, ActivityType::ProfileUpdated->value]));

                if ($references->isNotEmpty()) {
                    $q->orWhere(fn ($q) => $q->whereIn('subject_reference', $references)
                        ->whereIn('event_type', array_map(fn ($t) => $t->value, self::ADVERTISEMENT_TYPES)));
                }
            })
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->limit(1000)
            ->get();

        // Keep the first login after each activation and the most recent
        // logins; drop the long middle so the sequence stays readable.
        $firstLoginIds = $this->firstLoginsAfterActivation($member);
        $logins = $events->where('event_type', ActivityType::LoginSucceeded->value);
        $keepLogins = $logins->sortByDesc('occurred_at')->take(self::LOGIN_LIMIT)->pluck('id')->merge($firstLoginIds);

        $points = $events
            ->reject(fn (TrackingEvent $e) => $e->event_type === ActivityType::LoginSucceeded->value && ! $keepLogins->contains($e->id))
            ->map(fn (TrackingEvent $e) => EvidencePoint::fromTrackingEvent(
                $e,
                $member,
                in_array($e->id, $firstLoginIds, true) ? 'Member login (first after advertisement activation)' : null,
            ));

        // The evidentiary records themselves, rather than their log echoes.
        $records = Record::query()
            ->where('user_id', $member->id)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get()
            ->map(fn (Record $r) => EvidencePoint::fromRecord($r, $member));

        return $points->concat($records)
            // Same-second events keep their write order: activity rows before
            // records, then by id (zero-padded — "te:10" must follow "te:9").
            ->sortBy(fn (EvidencePoint $p) => $p->occurredAt->format('Y-m-d H:i:s.u')
                .'|'.(str_starts_with($p->key, 'fr:') ? '2' : '1')
                .'|'.str_pad(substr($p->key, 3), 12, '0', STR_PAD_LEFT))
            ->values();
    }

    /**
     * The account's first sign-in ever, from whichever log holds the earlier
     * row. Read from that row alone.
     */
    public function firstLogin(User $member): ?EvidencePoint
    {
        $event = TrackingEvent::query()->with('actor')
            ->where('event_type', ActivityType::LoginSucceeded->value)
            ->where('actor_user_id', $member->id)
            ->orderBy('occurred_at')->orderBy('id')->first();

        $session = \App\Models\LoginSession::query()
            ->where('user_id', $member->id)->where('auth_event', 'login')
            ->orderBy('occurred_at')->orderBy('id')->first();

        $login = match (true) {
            $event && $session => $session->occurred_at->lt($event->occurred_at->copy()->subSeconds(5)) ? $session : $event,
            default            => $event ?? $session,
        };

        return $login ? $this->fulfillment->evidence($login, $member, 'Member first login') : null;
    }

    /** @return list<int> */
    private function firstLoginsAfterActivation(User $member): array
    {
        return $this->fulfillment->forMember($member)
            ->map(fn (array $s) => $s['first_login']?->key)
            ->filter(fn ($k) => is_string($k) && str_starts_with($k, 'te:'))
            ->map(fn ($k) => (int) substr($k, 3))
            ->unique()
            ->values()
            ->all();
    }
}
