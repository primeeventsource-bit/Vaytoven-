<?php

namespace App\Models;

use App\Enums\MemberServiceOrderStatus;
use App\Enums\MemberServicePackage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MemberServiceOrder extends Model
{
    /** How long a payment link stays usable. */
    public const LINK_TTL_DAYS = 7;

    /** Stop accepting attempts after this many declines on one order. */
    public const MAX_PAYMENT_ATTEMPTS = 10;

    protected $fillable = [
        'reference', 'first_name', 'last_name', 'email', 'phone',
        'package', 'weeks', 'price_per_week_cents', 'total_cents', 'currency',
        'status', 'link_expires_at', 'paid_at',
        'nmi_transaction_id', 'nmi_authcode', 'nmi_response_text',
        'card_last_four', 'card_type', 'payment_attempts',
        'submitted_ip', 'user_agent', 'created_by_user_id', 'staff_notes',
    ];

    protected function casts(): array
    {
        return [
            'package'              => MemberServicePackage::class,
            'status'               => MemberServiceOrderStatus::class,
            'weeks'                => 'integer',
            'price_per_week_cents' => 'integer',
            'total_cents'          => 'integer',
            'payment_attempts'     => 'integer',
            'link_expires_at'      => 'datetime',
            'paid_at'              => 'datetime',
        ];
    }

    /**
     * A random public reference, e.g. VTN-7QK2M4XP.
     *
     * Deliberately not sequential. The reference appears in a URL that is
     * emailed and forwarded; a guessable one would let anyone enumerate other
     * members' names, phone numbers and amounts owed.
     */
    public static function generateReference(): string
    {
        do {
            // Crockford-ish alphabet: no I, O, 1 or 0 to survive being read
            // aloud over the phone, which is exactly how this will be used.
            $body = substr(str_replace(
                ['I', 'O', '1', '0'], '',
                Str::upper(Str::random(24)).'23456789',
            ), 0, 8);

            $reference = 'VTN-'.$body;
        } while (static::query()->where('reference', $reference)->exists());

        return $reference;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function totalDollars(): string
    {
        return number_format($this->total_cents / 100, 2);
    }

    public function pricePerWeekDollars(): string
    {
        return number_format($this->price_per_week_cents / 100, 2);
    }

    public function isExpired(): bool
    {
        return $this->link_expires_at !== null
            && $this->link_expires_at->isPast()
            && $this->status !== MemberServiceOrderStatus::Paid;
    }

    /**
     * Status as it should READ, accounting for a lapsed link.
     *
     * The stored status only changes when something acts on the order. Without
     * this, an order whose link quietly expired still reports "awaiting
     * payment" everywhere until a sweep runs — and there is no scheduler on
     * this environment, so no sweep runs at all.
     */
    public function effectiveStatus(): MemberServiceOrderStatus
    {
        return $this->isExpired() ? MemberServiceOrderStatus::Expired : $this->status;
    }

    /** Can the member still pay right now? */
    public function isPayable(): bool
    {
        return $this->effectiveStatus()->isPayable()
            && $this->payment_attempts < self::MAX_PAYMENT_ATTEMPTS;
    }

    public function paymentUrl(): string
    {
        return route('member-payment.show', $this->reference);
    }
}
