<?php

namespace App\Models;

use App\Enums\PaymentProcessor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChargebackDispute extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'user_id',
        'processor',
        'external_dispute_id',
        'amount_cents',
        'reason',
        'status',
        'evidence_due_by',
        'evidence_submitted_at',
        'bundle_path',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'processor' => PaymentProcessor::class,
            'evidence_due_by' => 'datetime',
            'evidence_submitted_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
