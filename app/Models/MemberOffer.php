<?php

namespace App\Models;

use App\Enums\MemberOfferStatus;
use App\Enums\OfferDirection;
use App\Enums\OfferKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An offer against a listing, in either direction:
 *
 *   to_member  — Vaytoven proposes a booking to the member who owns the
 *                managed listing; the MEMBER accepts or declines.
 *   from_buyer — a buyer submits an inquiry or offer on a public listing;
 *                the LISTING OWNER accepts or declines.
 *
 * Buyer submissions expire 24 hours after the exact moment they were
 * submitted. Everything captured at submission — amount, message, timestamp,
 * IP — is retained after expiry for recordkeeping; expiry only moves `status`.
 */
class MemberOffer extends Model
{
    /** How long a buyer submission stays open. */
    public const BUYER_OFFER_TTL_HOURS = 24;

    protected $fillable = [
        'direction',
        'kind',
        'member_user_id',
        'buyer_user_id',
        'property_id',
        'sent_by_user_id',
        'proposed_check_in',
        'proposed_check_out',
        'proposed_guests',
        'payout_to_member_cents',
        'offer_amount_cents',
        'status',
        'instructions',
        'buyer_message',
        'submitted_ip',
        'sent_at',
        'expires_at',
        'responded_at',
        'member_response_notes',
    ];

    protected function casts(): array
    {
        return [
            'direction' => OfferDirection::class,
            'kind' => OfferKind::class,
            'proposed_check_in' => 'date',
            'proposed_check_out' => 'date',
            'proposed_guests' => 'integer',
            'payout_to_member_cents' => 'integer',
            'offer_amount_cents' => 'integer',
            'status' => MemberOfferStatus::class,
            'sent_at' => 'datetime',
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    // --- Relations --------------------------------------------------------

    /** Recipient of an outbound offer. */
    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_user_id');
    }

    /** Originator of an inbound submission. */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    // --- Scopes -----------------------------------------------------------

    public function scopeFromBuyers(Builder $query): Builder
    {
        return $query->where('direction', OfferDirection::FromBuyer->value);
    }

    public function scopeToMembers(Builder $query): Builder
    {
        return $query->where('direction', OfferDirection::ToMember->value);
    }

    /** Still awaiting a decision. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            MemberOfferStatus::Pending->value,
            MemberOfferStatus::Active->value,
        ]);
    }

    /**
     * Submissions on listings this user owns.
     *
     * Ownership is `properties.host_id`. Staff never reach the owner
     * dashboard — they use the admin view, which is permission-gated instead.
     */
    public function scopeForListingsOwnedBy(Builder $query, User $owner): Builder
    {
        return $query->whereHas('property', fn (Builder $p) => $p->where('host_id', $owner->id));
    }

    // --- Buyer-submission behaviour ---------------------------------------

    public function isFromBuyer(): bool
    {
        return $this->direction === OfferDirection::FromBuyer;
    }

    /**
     * Past its expiry while still open. Read-through so a dashboard never
     * shows an offer as live when the sweep has not run yet — the sweep
     * persists the same verdict, it does not define it.
     */
    public function isExpired(): bool
    {
        return $this->status === MemberOfferStatus::Expired
            || ($this->status->isOpen()
                && $this->expires_at !== null
                && $this->expires_at->isPast());
    }

    /** The status a viewer should see right now, expiry included. */
    public function effectiveStatus(): MemberOfferStatus
    {
        return $this->isExpired() ? MemberOfferStatus::Expired : $this->status;
    }

    /** Can the listing owner still act on this? */
    public function isAwaitingOwner(): bool
    {
        return $this->isFromBuyer() && $this->status->isOpen() && ! $this->isExpired();
    }

    /** Nights between check-in and check-out. */
    public function nights(): int
    {
        return (int) $this->proposed_check_in?->diffInDays($this->proposed_check_out);
    }
}
