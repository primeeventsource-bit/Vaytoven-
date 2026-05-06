<?php

namespace App\Models;

use App\Enums\PaymentIntentStatus;
use App\Enums\PaymentProcessor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentIntent extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'processor',
        'external_intent_id',
        'client_secret',
        'amount_cents',
        'currency',
        'status',
        'metadata',
    ];

    protected $hidden = [
        // client_secret is page-scoped (Stripe Elements needs it to confirm a
        // single PaymentIntent), but should never leak into JSON resources by
        // default. Views read it explicitly via $intent->client_secret.
        'client_secret',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'processor' => PaymentProcessor::class,
            'status' => PaymentIntentStatus::class,
            'metadata' => 'array',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }
}
