<?php

namespace App\Models;

use App\Support\Reference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A role on the Careers page.
 *
 * Nothing is seeded. If no operator has published a role, the public page says
 * so — inventing openings to make a page look populated would waste real
 * applicants' time.
 */
class JobOpening extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug', 'title', 'department', 'location', 'employment_type',
        'summary', 'description', 'requirements',
        'is_published', 'closed_at', 'sort_order', 'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'closed_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function booted(): void
    {
        static::creating(function (self $job) {
            $job->slug ??= Reference::uniqueSlug(
                $job->title,
                fn (string $slug) => static::query()->where('slug', $slug)->exists(),
            );
        });
    }

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    /** Published and not yet closed — the only roles the public page shows. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('is_published', true)->whereNull('closed_at');
    }

    public function isOpen(): bool
    {
        return $this->is_published && $this->closed_at === null;
    }

    public function employmentTypeLabel(): string
    {
        return ucwords(str_replace('_', '-', $this->employment_type));
    }
}
