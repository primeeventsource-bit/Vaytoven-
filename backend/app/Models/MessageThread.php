<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessageThread extends Model
{
    protected $fillable = [
        'uuid', 'property_id', 'booking_id', 'guest_id', 'host_id',
        'last_message_at', 'archived_by_guest', 'archived_by_host',
    ];

    protected $casts = [
        'last_message_at'    => 'datetime',
        'archived_by_guest'  => 'boolean',
        'archived_by_host'   => 'boolean',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guest_id');
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'thread_id');
    }
}
