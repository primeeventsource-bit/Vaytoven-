<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Layer 3 of the configuration system: on/off gates for whole capabilities
 * ('ai_chat', 'mlp_enquiry', 'processor.nmi', …). Resolution — including
 * scope and rollout_pct handling — lives in FeatureFlagService / feature().
 */
class FeatureFlag extends Model
{
    protected $fillable = [
        'key', 'enabled', 'scope', 'scope_value', 'description', 'rollout_pct', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'rollout_pct' => 'integer',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
