<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A vacation-property exchange or banking network. Includes both true
 * third-party exchanges (Interval International, RCI) and developer-internal
 * programs (HGV Max, Club Wyndham), distinguished by `type`.
 *
 * Lookup-only — read frequently, written rarely. Don't put business logic here.
 */
class ExchangeNetwork extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'type',
        'website_url',
        'notes',
    ];

    public function developers(): BelongsToMany
    {
        return $this->belongsToMany(
            PropertyDeveloper::class,
            'developer_exchange_mappings',
            'exchange_network_id',
            'developer_id',
        )->withPivot(['confidence', 'priority', 'conditions', 'notes'])->withTimestamps();
    }
}
