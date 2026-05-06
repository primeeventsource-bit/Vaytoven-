<?php

namespace App\Models;

use App\Enums\PaymentProcessor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    use HasFactory;

    protected $fillable = [
        'charge_id',
        'booking_id',
        'actor_user_id',
        'processor',
        'external_refund_id',
        'amount_cents',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'processor' => PaymentProcessor::class,
        ];
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
