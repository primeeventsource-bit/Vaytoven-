<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = [
        'booking_id', 'property_id', 'reviewer_id', 'reviewee_id', 'type',
        'overall_rating', 'cleanliness_rating', 'accuracy_rating',
        'communication_rating', 'location_rating', 'value_rating',
        'public_comment', 'private_comment', 'published_at', 'hidden_by_admin',
    ];

    protected $casts = [
        'published_at'    => 'datetime',
        'hidden_by_admin' => 'boolean',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function reviewee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewee_id');
    }
}
