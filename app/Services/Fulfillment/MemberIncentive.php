<?php

namespace App\Services\Fulfillment;

use App\Enums\ActivityType;
use App\Enums\UserRole;
use App\Models\AdvertisementFulfillmentRecord as Record;
use App\Models\Property;
use App\Models\User;
use App\Services\Tracking\ActivityRecorder;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Presented → Acknowledged → Delivered, for the Dining Rewards incentive.
 *
 * Three facts with three different standards of proof:
 *   - PRESENTED: the server rendered the incentive to the signed-in member.
 *   - ACKNOWLEDGED: the member pressed one of its buttons.
 *   - DELIVERED: somebody holds evidence the certificate itself reached the
 *     member (a provider fulfilment reference). Showing the artwork is never
 *     delivery, and nothing here infers delivery from anything the member did.
 *
 * None of them is advertisement access or acceptance.
 */
class MemberIncentive
{
    public const SESSION_PENDING = 'vyt_incentive_pending';

    public const STATUS_NOT_PRESENTED = 'not_presented';
    public const STATUS_PRESENTED     = 'presented';
    public const STATUS_ACKNOWLEDGED  = 'acknowledged';
    public const STATUS_DELIVERED     = 'delivered';

    public function __construct(
        private readonly AdvertisementFulfillment $fulfillment,
        private readonly ActivityRecorder $activity,
    ) {
    }

    /**
     * Managed Listing Program clients: members, and any non-staff account that
     * owns a listing. Staff never — an admin signing in is not a client.
     */
    public function isEligible(?User $user): bool
    {
        if (! $user || $user->isStaff()) {
            return false;
        }

        return $user->role === UserRole::Member
            || Property::query()->where('host_id', $user->id)->exists();
    }

    public function needsPresentation(?User $user): bool
    {
        return $this->isEligible($user) && ! $this->find(Record::EVENT_INCENTIVE_PRESENTED, $user);
    }

    /** Written when the incentive screen is rendered for the member. Once only. */
    public function present(Request $request, User $member): Record
    {
        $this->guard($member);

        if ($existing = $this->find(Record::EVENT_INCENTIVE_PRESENTED, $member)) {
            return $existing;
        }

        $onFirstLogin = (bool) $request->session()->get(self::SESSION_PENDING.'_first_login', false);

        $event = $this->activity->record(
            ActivityType::MemberIncentivePresented,
            $request,
            subjectType: 'incentive',
            subjectReference: DiningRewardsIncentive::KEY,
            result: 'completed',
            metadata: [
                'incentive_version' => DiningRewardsIncentive::VERSION,
                'presentation_hash' => DiningRewardsIncentive::presentationHash(),
                'on_first_login'    => $onFirstLogin,
            ],
            actor: $member,
        );

        return $this->fulfillment->insert([
            'event'             => Record::EVENT_INCENTIVE_PRESENTED,
            'dedupe_key'        => $this->key(Record::EVENT_INCENTIVE_PRESENTED, $member),
            'tracking_event_id' => $event?->id,
            'metadata'          => ['on_first_login' => $onFirstLogin],
        ] + DiningRewardsIncentive::columns()
          + $this->fulfillment->memberColumns($member)
          + $this->fulfillment->requestColumns($request, $member));
    }

    /**
     * The member pressed VIEW or CONTINUE & CLAIM. Refused if the incentive
     * was never presented to them; a second press changes nothing.
     */
    public function acknowledge(Request $request, User $member, string $choice): Record
    {
        $this->guard($member);

        if ($existing = $this->find(Record::EVENT_INCENTIVE_ACKNOWLEDGED, $member)) {
            return $existing;
        }

        $presented = $this->find(Record::EVENT_INCENTIVE_PRESENTED, $member);

        if (! $presented) {
            throw new RuntimeException('The incentive has not been presented to this member.');
        }

        $button = $choice === 'view' ? DiningRewardsIncentive::VIEW_BUTTON : DiningRewardsIncentive::CONTINUE_BUTTON;

        $event = $this->activity->record(
            ActivityType::MemberIncentiveAcknowledged,
            $request,
            subjectType: 'incentive',
            subjectReference: DiningRewardsIncentive::KEY,
            result: 'completed',
            metadata: ['button' => $button, 'incentive_version' => DiningRewardsIncentive::VERSION],
            actor: $member,
        );

        return $this->fulfillment->insert([
            'event'             => Record::EVENT_INCENTIVE_ACKNOWLEDGED,
            'dedupe_key'        => $this->key(Record::EVENT_INCENTIVE_ACKNOWLEDGED, $member),
            'tracking_event_id' => $event?->id,
            'metadata'          => ['button' => $button, 'presented_record' => $presented->record_uuid],
        ] + DiningRewardsIncentive::columns()
          + $this->fulfillment->memberColumns($member)
          + $this->fulfillment->requestColumns($request, $member));
    }

    /**
     * Staff record delivery, against evidence: how it was delivered and the
     * provider's reference for it. Admin activity, never the member's.
     */
    public function recordDelivery(User $member, User $staff, string $method, string $reference, ?string $note, Request $request): Record
    {
        if (! $staff->isStaff()) {
            throw new RuntimeException('Only staff can record delivery.');
        }

        if (! $this->isEligible($member)) {
            throw new RuntimeException('This account is not a Managed Listing Program client.');
        }

        if (trim($reference) === '') {
            throw new RuntimeException('Delivery needs the provider reference that proves it.');
        }

        if ($existing = $this->find(Record::EVENT_INCENTIVE_DELIVERED, $member)) {
            return $existing;
        }

        $event = $this->activity->record(
            ActivityType::MemberIncentiveDelivered,
            $request,
            subjectType: 'user',
            subjectReference: (string) $member->id,
            result: 'completed',
            metadata: ['incentive' => DiningRewardsIncentive::KEY, 'method' => $method, 'reference' => $reference],
            actor: $staff,
        );

        return $this->fulfillment->insert([
            'event'               => Record::EVENT_INCENTIVE_DELIVERED,
            'dedupe_key'          => $this->key(Record::EVENT_INCENTIVE_DELIVERED, $member),
            'tracking_event_id'   => $event?->id,
            'recorded_by_user_id' => $staff->id,
            'delivery_method'     => mb_substr($method, 0, 40),
            'delivery_reference'  => mb_substr($reference, 0, 160),
            'correction_note'     => $note,
        ] + DiningRewardsIncentive::columns()
          + $this->fulfillment->memberColumns($member)
          // The staff member's request, not the member's: no comparison.
          + $this->fulfillment->requestColumns($request));
    }

    /**
     * @return array{presented: ?Record, acknowledged: ?Record, delivered: ?Record, status: string, eligible: bool}
     */
    public function state(User $member): array
    {
        $presented    = $this->find(Record::EVENT_INCENTIVE_PRESENTED, $member);
        $acknowledged = $this->find(Record::EVENT_INCENTIVE_ACKNOWLEDGED, $member);
        $delivered    = $this->find(Record::EVENT_INCENTIVE_DELIVERED, $member);

        return [
            'eligible'     => $this->isEligible($member),
            'presented'    => $presented,
            'acknowledged' => $acknowledged,
            'delivered'    => $delivered,
            'status'       => match (true) {
                $delivered !== null    => self::STATUS_DELIVERED,
                $acknowledged !== null => self::STATUS_ACKNOWLEDGED,
                $presented !== null    => self::STATUS_PRESENTED,
                default                => self::STATUS_NOT_PRESENTED,
            },
        ];
    }

    public static function statusLabel(string $status): string
    {
        return strtoupper(str_replace('_', ' ', $status));
    }

    private function guard(User $member): void
    {
        if (! $this->isEligible($member)) {
            throw new RuntimeException('The incentive is only for Managed Listing Program clients.');
        }
    }

    private function find(string $event, User $member): ?Record
    {
        return Record::query()->where('dedupe_key', $this->key($event, $member))->first();
    }

    private function key(string $event, User $member): string
    {
        return $event.':user:'.$member->id.':'.DiningRewardsIncentive::KEY;
    }
}
