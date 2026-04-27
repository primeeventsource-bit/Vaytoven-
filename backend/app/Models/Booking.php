<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    use HasFactory;

    public const STATUSES = ['pending', 'confirmed', 'checked_in', 'completed', 'cancelled', 'declined', 'expired'];

    protected $fillable = [
        'uuid', 'confirmation_code', 'property_id', 'guest_id', 'host_id',
        'check_in', 'check_out', 'nights',
        'guests', 'adults', 'children', 'infants', 'pets',
        'base_price_cents', 'cleaning_fee_cents', 'extra_guest_fee_cents',
        'subtotal_cents', 'guest_service_fee_cents', 'host_service_fee_cents',
        'tax_cents', 'total_cents', 'host_payout_cents', 'currency',
        'cancellation_policy', 'status', 'payment_status',
        'guest_message', 'host_note', 'cancellation_reason', 'cancelled_by', 'cancelled_at',
        'stripe_payment_intent_id', 'stripe_charge_id',
        'confirmed_at', 'checked_in_at', 'checked_out_at', 'completed_at',
    ];

    protected $casts = [
        'check_in'           => 'date',
        'check_out'          => 'date',
        'cancelled_at'       => 'datetime',
        'confirmed_at'       => 'datetime',
        'checked_in_at'      => 'datetime',
        'checked_out_at'     => 'datetime',
        'completed_at'       => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guest_id');
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function payout(): HasOne
    {
        return $this->hasOne(Payout::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────

    public function scopeUpcoming(Builder $q): Builder
    {
        return $q->whereIn('status', ['confirmed', 'checked_in'])
            ->where('check_out', '>=', today());
    }

    public function scopePast(Builder $q): Builder
    {
        return $q->whereIn('status', ['completed', 'cancelled', 'expired', 'declined'])
            ->orWhere('check_out', '<', today());
    }

    // ─── State helpers ────────────────────────────────────────────

    public function isCancellable(): bool
    {
        return in_array($this->status, ['pending', 'confirmed'], true)
            && $this->check_in->isFuture();
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function getDateRangeAttribute(): string
    {
        return $this->check_in->format('M j') . ' → ' . $this->check_out->format('M j, Y');
    }

    public static function generateConfirmationCode(): string
    {
        // VYT-XXXXXX (6 alphanumeric, no ambiguous characters)
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = 'VYT-';
        for ($i = 0; $i < 6; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $code;
    }
}
