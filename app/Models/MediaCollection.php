<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A named folder in the shared photo library — "Pool shots", "Exteriors".
 *
 * Deliberately one flat level rather than a tree. A nested folder structure is
 * more to build, more to explain, and the thing staff actually need is to find
 * the good exterior photos in two clicks. Nesting can be added later if a flat
 * list genuinely stops scaling; starting there would be guessing.
 */
class MediaCollection extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description', 'created_by_user_id'];

    protected static function booted(): void
    {
        // Slugged from the name, and kept unique by suffixing rather than by
        // refusing: two people naming a collection "Pools" a month apart is
        // normal, and an error at that moment helps nobody.
        static::creating(function (self $collection) {
            if ($collection->slug) {
                return;
            }

            $base = Str::slug($collection->name) ?: 'collection';
            $slug = $base;
            $n    = 2;

            while (static::where('slug', $slug)->exists()) {
                $slug = $base.'-'.$n++;
            }

            $collection->slug = $slug;
        });
    }

    public function assets(): HasMany
    {
        return $this->hasMany(MediaAsset::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** The image shown on the folder tile. */
    public function coverAsset(): ?MediaAsset
    {
        return $this->assets()->orderBy('id')->first();
    }
}
