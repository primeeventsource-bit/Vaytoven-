<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single observed view of a property listing.
 *
 * Operational analytics — the source of truth for the host/member dashboard
 * "views" panel and the geo map. Writes happen in PropertyBrowseController::show
 * via a GeoIpService lookup. Failures are swallowed (FR-10.5) so view tracking
 * can never block a page render.
 *
 * Not the same as tracking_events: that table is hash-chained chargeback
 * evidence, this one is a fast-query operational view.
 */
class PropertyView extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'property_id',
        'viewer_user_id',
        'visitor_id',
        'ip_address',
        'country',
        'region',
        'city',
        'latitude',
        'longitude',
        'user_agent',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'latitude'    => 'float',
            'longitude'   => 'float',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function viewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'viewer_user_id');
    }
}
