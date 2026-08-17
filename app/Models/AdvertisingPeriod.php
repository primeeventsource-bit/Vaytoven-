<?php

namespace App\Models;

use App\Enums\AdvertisingPeriodStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvertisingPeriod extends Model
{
    protected $fillable = [
        'member_service_order_id', 'property_id',
        'starts_at', 'ends_at', 'activated_at', 'activated_by_user_id',
        'status', 'paused_at', 'staff_notes',
    ];

    protected function casts(): array
    {
        return [
            'status'       => AdvertisingPeriodStatus::class,
            'starts_at'    => 'datetime',
            'ends_at'      => 'datetime',
            'activated_at' => 'datetime',
            'paused_at'    => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(MemberServiceOrder::class, 'member_service_order_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by_user_id');
    }

    /**
     * Status as it should READ, accounting for the clock.
     *
     * The stored status only changes when something acts on the row, and there
     * is no scheduler on this environment — so without this, a period that ran
     * out last week still reports "active" on every screen. Same read-through
     * pattern as MemberServiceOrder and MemberOffer.
     */
    public function effectiveStatus(): AdvertisingPeriodStatus
    {
        if (in_array($this->status, [
            AdvertisingPeriodStatus::Cancelled,
            AdvertisingPeriodStatus::Paused,
            AdvertisingPeriodStatus::Pending,
        ], true)) {
            return $this->status;
        }

        return $this->ends_at->isPast()
            ? AdvertisingPeriodStatus::Expired
            : AdvertisingPeriodStatus::Active;
    }

    public function isLive(): bool
    {
        return $this->effectiveStatus() === AdvertisingPeriodStatus::Active;
    }

    /**
     * Days left, rounded UP, never negative.
     *
     * Ceil rather than floor: a period ending in 9 hours has a day of
     * advertising still to run, and flooring would report "0 days left" on a
     * listing that is visibly still live. The member paid for the window, so
     * a part day counts as a day.
     *
     * Never negative either — "-6 days remaining" is a number nobody can act
     * on, and the expired status already carries that meaning.
     */
    public function daysRemaining(): int
    {
        if (! $this->isLive()) {
            return 0;
        }

        return max(0, (int) ceil(now()->diffInDays($this->ends_at, false)));
    }

    // --- scopes -----------------------------------------------------------

    /** Running right now, by the clock rather than the stored column. */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', AdvertisingPeriodStatus::Active)
            ->where('ends_at', '>', now());
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', AdvertisingPeriodStatus::Active)
            ->where('ends_at', '<=', now());
    }

    public function scopeAwaitingActivation(Builder $query): Builder
    {
        return $query->where('status', AdvertisingPeriodStatus::Pending);
    }
}
