<?php

namespace App\Models;

use App\Enums\AvailabilityWeekStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * One block of advertised time on a property.
 *
 * Called a "week" because that is what members and staff call it, but the
 * dates are what is stored and what is shown. Nothing assumes seven nights.
 */
class PropertyAvailabilityWeek extends Model
{
    /**
     * Declared here as well as in the schema: a database default is not
     * applied to the instance create() returns, so a freshly made week read
     * back a null status.
     */
    protected $attributes = [
        'status' => 'available',
    ];

    protected $fillable = [
        'property_id', 'starts_on', 'ends_on', 'status', 'notes', 'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on'   => 'date',
            'status'    => AvailabilityWeekStatus::class,
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /** What a traveler may see: public status, and not already finished. */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [
                AvailabilityWeekStatus::Available->value,
                AvailabilityWeekStatus::OfferPending->value,
            ])
            ->whereDate('ends_on', '>=', now()->toDateString());
    }

    public function nights(): int
    {
        return (int) $this->starts_on->diffInDays($this->ends_on);
    }

    /** "September 5–12, 2026", collapsing the month when both ends share it. */
    public function label(): string
    {
        $sameYear  = $this->starts_on->year === $this->ends_on->year;
        $sameMonth = $sameYear && $this->starts_on->month === $this->ends_on->month;

        if ($sameMonth) {
            return $this->starts_on->format('F j').'–'.$this->ends_on->format('j, Y');
        }

        return $sameYear
            ? $this->starts_on->format('F j').' – '.$this->ends_on->format('F j, Y')
            : $this->starts_on->format('F j, Y').' – '.$this->ends_on->format('F j, Y');
    }

    public function hasPassed(): bool
    {
        return $this->ends_on->isBefore(now()->startOfDay());
    }
}
