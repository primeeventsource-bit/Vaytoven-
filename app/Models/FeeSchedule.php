<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Named fee configuration (percentages are whole integers, money is integer
 * cents — NFR-1). The schedule referenced by setting('fees.active_schedule_id')
 * drives quote math; the fees.* scalar settings are the fallback.
 */
class FeeSchedule extends Model
{
    protected $fillable = [
        'name', 'guest_service_pct', 'host_commission_pct',
        'cleaning_fee_mode', 'flat_cleaning_fee_cents',
        'security_deposit_mode', 'security_deposit_cents',
        'applies_to', 'active', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'guest_service_pct' => 'integer',
            'host_commission_pct' => 'integer',
            'flat_cleaning_fee_cents' => 'integer',
            'security_deposit_cents' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
