<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A vacation-property developer brand (Marriott Vacation Club, Disney
 * Vacation Club, etc.). parent_brand groups by corporate owner.
 *
 * Looked up via ExchangeDetectionService::findDeveloperByName() during
 * auto-detection on enquiry / managed-listing creation.
 */
class PropertyDeveloper extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'parent_brand',
        'notes',
    ];

    public function exchanges(): BelongsToMany
    {
        return $this->belongsToMany(
            ExchangeNetwork::class,
            'developer_exchange_mappings',
            'developer_id',
            'exchange_network_id',
        )->withPivot(['confidence', 'priority', 'conditions', 'notes'])->withTimestamps();
    }

    public function exchangeMappings(): HasMany
    {
        return $this->hasMany(DeveloperExchangeMapping::class, 'developer_id');
    }
}
