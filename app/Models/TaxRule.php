<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jurisdictional tax rule. rate_bps is basis points (875 = 8.75%) so tax
 * math stays integer end-to-end: tax = intdiv(base_cents * rate_bps, 10000).
 */
class TaxRule extends Model
{
    protected $fillable = [
        'name', 'jurisdiction', 'country_code', 'region_code',
        'rate_bps', 'applies_to', 'active', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'rate_bps' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
