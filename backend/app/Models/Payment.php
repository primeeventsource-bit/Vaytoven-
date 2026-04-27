<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'booking_id', 'stripe_payment_intent_id', 'stripe_charge_id',
        'amount_cents', 'currency', 'status',
        'payment_method_details', 'refunded_cents', 'captured_at',
    ];

    protected $casts = [
        'payment_method_details' => 'array',
        'captured_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
