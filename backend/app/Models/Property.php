<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Property extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'host_id', 'title', 'slug', 'description',
        'property_type', 'room_type', 'max_guests', 'bedrooms', 'bathrooms', 'beds',
        'country_code', 'region', 'city', 'postal_code',
        'address_line1', 'address_line2', 'latitude', 'longitude',
        'base_price_cents', 'cleaning_fee_cents',
        'extra_guest_fee_cents', 'extra_guests_after', 'currency',
        'min_nights', 'max_nights', 'advance_notice_hours',
        'check_in_time', 'check_out_time',
        'instant_book', 'cancellation_policy',
        'status', 'published_at',
    ];

    protected $casts = [
        'instant_book'   => 'boolean',
        'latitude'       => 'float',
        'longitude'      => 'float',
        'bedrooms'       => 'float',
        'bathrooms'      => 'float',
        'rating_avg'     => 'float',
        'published_at'   => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(PropertyImage::class)->orderBy('sort_order');
    }

    public function coverImage()
    {
        return $this->hasOne(PropertyImage::class)->where('is_cover', true);
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class, 'property_amenities');
    }

    public function calendar(): HasMany
    {
        return $this->hasMany(PropertyCalendar::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class)->whereNotNull('published_at');
    }

    // ─── Scopes ───────────────────────────────────────────────────

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'active')->whereNotNull('published_at');
    }

    public function scopeInCity(Builder $q, string $country, string $city): Builder
    {
        return $q->where('country_code', $country)
            ->where('city', $city);
    }

    /**
     * Properties available for the given date range.
     * Excludes properties with overlapping confirmed bookings or blocked calendar dates.
     */
    public function scopeAvailableBetween(Builder $q, string $checkIn, string $checkOut): Builder
    {
        return $q->whereDoesntHave('bookings', function (Builder $b) use ($checkIn, $checkOut) {
            $b->whereIn('status', ['pending', 'confirmed', 'checked_in'])
              ->where('check_in', '<', $checkOut)
              ->where('check_out', '>', $checkIn);
        })->whereDoesntHave('calendar', function (Builder $c) use ($checkIn, $checkOut) {
            $c->whereBetween('date', [$checkIn, $checkOut])
              ->where('is_blocked_by_host', true);
        });
    }

    // ─── Helpers ──────────────────────────────────────────────────

    public function getPriceFormattedAttribute(): string
    {
        return '$' . number_format($this->base_price_cents / 100, 2);
    }

    public function isPublished(): bool
    {
        return $this->status === 'active' && $this->published_at !== null;
    }
}
