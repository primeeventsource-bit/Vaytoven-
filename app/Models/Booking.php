<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\CancellationPolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'traveler_id',
        'confirmation_code',
        'check_in_date',
        'check_out_date',
        'guests',
        'nightly_rate_cents',
        'nights',
        'subtotal_cents',
        'cleaning_fee_cents',
        'service_fee_cents',
        'tax_cents',
        'total_cents',
        'cancellation_policy',
        'status',
        'cancelled_at',
        'cancelled_reason',
        'payment_intent_id',
    ];

    protected function casts(): array
    {
        return [
            'check_in_date' => 'date',
            'check_out_date' => 'date',
            'guests' => 'integer',
            'nightly_rate_cents' => 'integer',
            'nights' => 'integer',
            'subtotal_cents' => 'integer',
            'cleaning_fee_cents' => 'integer',
            'service_fee_cents' => 'integer',
            'tax_cents' => 'integer',
            'total_cents' => 'integer',
            'status' => BookingStatus::class,
            'cancellation_policy' => CancellationPolicy::class,
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Booking $booking): void {
            if (empty($booking->confirmation_code)) {
                $booking->confirmation_code = static::generateConfirmationCode();
            }
        });

        static::created(function (Booking $booking): void {
            $booking->stateTransitions()->create([
                'from_state' => null,
                'to_state' => $booking->status->value,
                'actor_user_id' => null,
                'occurred_at' => now(),
            ]);
        });
    }

    /**
     * Generate a server-side confirmation code: VYT- + 6 uppercase alphanumeric (FR-3.3).
     * Globally unique — retries if the random draw collides with an existing code.
     */
    public static function generateConfirmationCode(): string
    {
        $alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

        do {
            $code = 'VYT-';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, 35)];
            }
        } while (static::where('confirmation_code', $code)->exists());

        return $code;
    }

    /**
     * Transition the booking to a new status, recording the change in
     * booking_state_transitions for full auditability (FR-3.2).
     */
    public function transitionTo(BookingStatus $next, ?int $actorUserId = null, ?string $reason = null): void
    {
        $previous = $this->status;

        $this->status = $next;
        if ($next === BookingStatus::Cancelled) {
            $this->cancelled_at = now();
            $this->cancelled_reason = $reason;
        }
        $this->save();

        $this->stateTransitions()->create([
            'from_state' => $previous?->value,
            'to_state' => $next->value,
            'actor_user_id' => $actorUserId,
            'reason' => $reason,
            'occurred_at' => now(),
        ]);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function traveler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traveler_id');
    }

    public function stateTransitions(): HasMany
    {
        return $this->hasMany(BookingStateTransition::class)->orderBy('occurred_at');
    }
}
