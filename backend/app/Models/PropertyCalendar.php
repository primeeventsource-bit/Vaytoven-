<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyCalendar extends Model
{
    protected $table = 'property_calendar';

    protected $fillable = [
        'property_id', 'date', 'is_available', 'is_blocked_by_host',
        'booking_id', 'price_override_cents', 'min_nights_override', 'note',
    ];

    protected $casts = [
        'date'                => 'date',
        'is_available'        => 'boolean',
        'is_blocked_by_host'  => 'boolean',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
