<?php

namespace App\Services\Offers;

use App\Enums\MemberOfferStatus;
use App\Enums\OfferDirection;
use App\Enums\OfferKind;
use App\Models\MemberOffer;
use App\Models\Property;
use App\Models\User;
use App\Services\AdminAuditLogService;
use Illuminate\Support\Facades\DB;

/**
 * Every state transition a buyer submission can make, in one place, so that
 * submission / accept / decline / expire all record the same audit shape.
 *
 * The audit trail is the point: an offer's amount, timestamp, and IP are
 * evidence in a dispute, so who changed what and when has to be reconstructable
 * from `admin_audit_logs` alone.
 */
class OfferService
{
    /**
     * Record a buyer's inquiry or offer on a listing.
     *
     * The 24-hour clock starts at the exact submission instant, not at
     * midnight and not on a rounded hour: an offer submitted 10 Aug 19:30
     * expires 11 Aug 19:30.
     */
    public function submit(
        Property $property,
        User $buyer,
        OfferKind $kind,
        ?int $amountCents,
        ?string $message,
        ?string $ipAddress,
    ): MemberOffer {
        $submittedAt = now();

        $offer = DB::transaction(fn () => MemberOffer::query()->create([
            'direction' => OfferDirection::FromBuyer,
            'kind' => $kind,
            'property_id' => $property->id,
            'buyer_user_id' => $buyer->id,
            // The listing owner is who must respond; denormalised so the
            // owner dashboard doesn't depend on the property surviving.
            'member_user_id' => $property->host_id,
            'offer_amount_cents' => $kind === OfferKind::Offer ? $amountCents : null,
            'buyer_message' => $message,
            'submitted_ip' => $ipAddress,
            'status' => MemberOfferStatus::Active,
            'sent_at' => $submittedAt,
            'expires_at' => $submittedAt->copy()->addHours(MemberOffer::BUYER_OFFER_TTL_HOURS),
        ]));

        AdminAuditLogService::log(
            actor: $buyer,
            action: 'offer.submit',
            subject: $offer,
            payload: [
                'kind' => $kind->value,
                'property_id' => $property->id,
                'listing_owner_id' => $property->host_id,
                'amount_cents' => $offer->offer_amount_cents,
                'submitted_at' => $submittedAt->toIso8601String(),
                'expires_at' => $offer->expires_at?->toIso8601String(),
                'ip' => $ipAddress,
            ],
            ipAddress: $ipAddress,
        );

        return $offer;
    }

    /** Listing owner (or an authorised admin) accepts a buyer submission. */
    public function accept(MemberOffer $offer, User $actor, ?string $notes, ?string $ipAddress): MemberOffer
    {
        return $this->respond($offer, $actor, MemberOfferStatus::Accepted, $notes, $ipAddress);
    }

    /** Listing owner (or an authorised admin) declines a buyer submission. */
    public function decline(MemberOffer $offer, User $actor, ?string $notes, ?string $ipAddress): MemberOffer
    {
        return $this->respond($offer, $actor, MemberOfferStatus::Declined, $notes, $ipAddress);
    }

    private function respond(
        MemberOffer $offer,
        User $actor,
        MemberOfferStatus $status,
        ?string $notes,
        ?string $ipAddress,
    ): MemberOffer {
        $from = $offer->status;

        $offer->update([
            'status' => $status,
            'responded_at' => now(),
            'member_response_notes' => $notes ?: null,
        ]);

        AdminAuditLogService::log(
            actor: $actor,
            action: 'offer.'.$status->value,
            subject: $offer,
            payload: [
                'old_value' => ['status' => $from->value],
                'new_value' => ['status' => $status->value],
                'property_id' => $offer->property_id,
                'buyer_user_id' => $offer->buyer_user_id,
                'amount_cents' => $offer->offer_amount_cents,
            ],
            ipAddress: $ipAddress,
        );

        return $offer;
    }

    /**
     * Flip every open offer past its expiry to EXPIRED, in both directions.
     *
     * Nothing else is touched: the amount, message, submission timestamp, and
     * IP all survive expiry, which is what makes an expired row still usable
     * as a record.
     *
     * @return int Rows expired.
     */
    public function expireOverdue(): int
    {
        $overdue = MemberOffer::query()
            ->open()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($overdue as $offer) {
            $offer->update(['status' => MemberOfferStatus::Expired]);
        }

        return $overdue->count();
    }
}
