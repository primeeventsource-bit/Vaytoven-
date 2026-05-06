<?php

namespace App\Models;

use App\Enums\PaymentProcessor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Charge extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_intent_id',
        'booking_id',
        'processor',
        'external_charge_id',
        'amount_cents',
        'currency',
        'captured',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'captured' => 'boolean',
            'processor' => PaymentProcessor::class,
            'metadata' => 'array',
        ];
    }

    public function paymentIntent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }
}
