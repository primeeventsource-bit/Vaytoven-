<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PropertyPhoto extends Model
{
    use HasFactory;

    /** Gallery sections, in the order they are shown. */
    public const CATEGORIES = [
        'exterior'    => 'Exterior',
        'bedroom'     => 'Bedrooms',
        'bathroom'    => 'Bathrooms',
        'kitchen'     => 'Kitchen',
        'living'      => 'Living area',
        'amenities'   => 'Amenities',
        'pool_resort' => 'Pool / resort',
        'views'       => 'Views',
        'other'       => 'Other',
    ];

    protected $fillable = [
        'property_id',
        'disk',
        'path',
        'original_path',
        'url',
        'sort_order',
        'is_cover',
        'caption',
        'category',
        'alt_text',
        'original_name',
        'mime_type',
        'size_bytes',
        'width',
        'height',
        'sha256',
        'rotation',
        'crop_x',
        'crop_y',
        'crop_w',
        'crop_h',
        'uploaded_by_user_id',
    ];

    protected $attributes = [
        'category' => 'other',
        'is_cover' => false,
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_cover'   => 'boolean',
            'size_bytes' => 'integer',
            'width'      => 'integer',
            'height'     => 'integer',
            'rotation'   => 'integer',
            'crop_x'     => 'float',
            'crop_y'     => 'float',
            'crop_w'     => 'float',
            'crop_h'     => 'float',
        ];
    }

    /** Whether any edit has been applied, i.e. whether "Reset" has anything to undo. */
    public function isEdited(): bool
    {
        return (int) $this->rotation !== 0 || $this->crop_w !== null;
    }

    /**
     * The crop box as the editor wants it: four fractions, or null.
     *
     * Returned all-or-nothing because a half-stored box (a width with no left
     * edge) would have the editor draw a rectangle in the wrong place, which
     * looks like the saved crop moved on its own.
     *
     * @return array{x: float, y: float, w: float, h: float}|null
     */
    public function cropBox(): ?array
    {
        if ($this->crop_w === null || $this->crop_h === null) {
            return null;
        }

        return [
            'x' => (float) $this->crop_x,
            'y' => (float) $this->crop_y,
            'w' => (float) $this->crop_w,
            'h' => (float) $this->crop_h,
        ];
    }

    /**
     * Whether the pristine upload is still on the disk.
     *
     * Rotation and crop replay against the original, so a photo whose original
     * has gone can still be shown but can no longer be edited. The editor asks
     * this so it can say that up front instead of failing on save.
     */
    public function originalExists(): bool
    {
        return $this->original_path !== null
            && Storage::disk($this->disk)->exists($this->original_path);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? 'Other';
    }

    public function isUploaded(): bool
    {
        return $this->path !== null;
    }

    /**
     * Where to point an <img> at.
     *
     * Uploaded photos live in a PRIVATE bucket, so they are served through the
     * app rather than linked directly. Signed URLs were the alternative and
     * they expire, which makes them useless for anything cached or shared.
     * Rows that predate uploads still carry an external url and keep working.
     */
    public function displayUrl(): ?string
    {
        if ($this->isUploaded()) {
            return route('properties.photo', $this);
        }

        return $this->url;
    }

    public function fileExists(): bool
    {
        return $this->isUploaded()
            && Storage::disk($this->disk)->exists($this->path);
    }

    /** What a screen reader announces. Falls back so it is never empty. */
    public function altText(): string
    {
        return $this->alt_text
            ?: $this->caption
            ?: trim($this->categoryLabel().' — '.($this->property?->title ?? 'property photo'));
    }

    public function sizeForHumans(): string
    {
        $bytes = (int) $this->size_bytes;

        return match (true) {
            $bytes >= 1048576 => number_format($bytes / 1048576, 1).' MB',
            $bytes >= 1024    => number_format($bytes / 1024, 0).' KB',
            default           => $bytes.' B',
        };
    }

    /**
     * Make this the cover, and demote whatever held it.
     *
     * Done in one transaction because "exactly one cover" cannot be expressed
     * as a partial unique index on MySQL. Two rows both marked cover would give
     * the search card a coin flip rather than a choice.
     */
    public function makeCover(): void
    {
        DB::transaction(function () {
            static::where('property_id', $this->property_id)
                ->where('id', '!=', $this->getKey())
                ->where('is_cover', true)
                ->update(['is_cover' => false]);

            $this->forceFill(['is_cover' => true])->save();
        });
    }

    /**
     * The photo a search card should use: the chosen cover, else the first.
     *
     * Falling back rather than showing nothing matters because a listing with
     * photos and no cover set is the normal state right after an upload.
     */
    public static function coverFor(Property $property): ?self
    {
        return static::where('property_id', $property->id)
            ->orderByDesc('is_cover')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }
}
