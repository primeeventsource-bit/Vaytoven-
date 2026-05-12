<?php

namespace App\Models;

use App\Enums\CancellationPolicy;
use App\Enums\PropertyStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'host_id',
        'listing_source',
        'title',
        'description',
        'latitude',
        'longitude',
        'address_line',
        'city',
        'region',
        'country',
        'postal_code',
        'capacity',
        'bedrooms',
        'beds',
        'bathrooms',
        'base_nightly_cents',
        'cleaning_fee_cents',
        'cancellation_policy',
        'minimum_nights',
        'status',
        'payout_account_id',
        'converted_from_enquiry_id',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'bathrooms' => 'decimal:1',
            'base_nightly_cents' => 'integer',
            'cleaning_fee_cents' => 'integer',
            'capacity' => 'integer',
            'bedrooms' => 'integer',
            'beds' => 'integer',
            'minimum_nights' => 'integer',
            'status' => PropertyStatus::class,
            'cancellation_policy' => CancellationPolicy::class,
            // Vacation Club Exchange Detection snapshot — written by
            // ExchangeDetectionObserver when listing_source = 'managed'.
            'exchange_detection' => 'array',
        ];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class, 'property_amenities');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(PropertyPhoto::class)->orderBy('sort_order');
    }

    public function views(): HasMany
    {
        return $this->hasMany(PropertyView::class);
    }

    /**
     * The members-enquiry this listing was converted from (managed-listing path).
     * Null for normal host-listed properties (listing_source = 'host').
     */
    public function convertedFromEnquiry(): BelongsTo
    {
        return $this->belongsTo(MemberEnquiry::class, 'converted_from_enquiry_id');
    }
}
