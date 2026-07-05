<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Admin-editable cancellation policy (table: cancellation_policies).
 *
 * Named ...Config to avoid colliding with App\Enums\CancellationPolicy,
 * which bookings/properties cast to; `code` matches that enum's values.
 *
 * refund_tiers: ordered array of {days_before: int, refund_pct: int 0..100}.
 * RefundCalculator picks the first tier whose days_before threshold the
 * cancellation beats (>= days_before * 24 hours until check-in); no tier
 * matched = 0% refund.
 */
class CancellationPolicyConfig extends Model
{
    protected $table = 'cancellation_policies';

    protected $fillable = [
        'code', 'name', 'description', 'refund_tiers', 'is_default', 'active', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'refund_tiers' => 'array',
            'is_default' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
