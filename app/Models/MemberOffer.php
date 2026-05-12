<?php

namespace App\Models;

use App\Enums\MemberOfferStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Booking offer Vaytoven extends to a member against their managed-listing
 * points inventory. Member accepts/declines via MemberOfferController.
 */
class MemberOffer extends Model
{
    protected $fillable = [
        'member_user_id',
        'property_id',
        'sent_by_user_id',
        'proposed_check_in',
        'proposed_check_out',
        'proposed_guests',
        'payout_to_member_cents',
        'status',
        'instructions',
        'sent_at',
        'expires_at',
        'responded_at',
        'member_response_notes',
    ];

    protected function casts(): array
    {
        return [
            'proposed_check_in'       => 'date',
            'proposed_check_out'      => 'date',
            'proposed_guests'         => 'integer',
            'payout_to_member_cents'  => 'integer',
            'status'                  => MemberOfferStatus::class,
            'sent_at'                 => 'datetime',
            'expires_at'              => 'datetime',
            'responded_at'            => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_user_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    /** Nights between check-in and check-out. */
    public function nights(): int
    {
        return (int) $this->proposed_check_in?->diffInDays($this->proposed_check_out);
    }
}
