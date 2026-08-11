<?php

namespace App\Models;

use App\Support\Reference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A company announcement on /press.
 *
 * Nothing is seeded here either. Fabricated coverage, awards or partnerships
 * would be a misrepresentation, so the page ships empty until Vaytoven has
 * something real to announce.
 */
class PressRelease extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug', 'title', 'excerpt', 'body', 'media_contact_email',
        'is_published', 'published_at', 'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function booted(): void
    {
        static::creating(function (self $release) {
            $release->slug ??= Reference::uniqueSlug(
                $release->title,
                fn (string $slug) => static::query()->where('slug', $slug)->exists(),
            );
        });
    }

    /** Published, and not post-dated. */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
