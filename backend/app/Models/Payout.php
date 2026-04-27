<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payout extends Model
{
    protected $fillable = [
        'booking_id', 'host_id', 'payout_method_id',
        'stripe_transfer_id', 'amount_cents', 'currency', 'status',
        'scheduled_for', 'released_at', 'failure_reason', 'retry_count',
    ];

    protected $casts = [
        'scheduled_for' => 'datetime',
        'released_at'   => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function payoutMethod(): BelongsTo
    {
        return $this->belongsTo(PayoutMethod::class);
    }
}
