<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * One image in the shared library.
 *
 * Stored exactly like a property photo — an optimised WebP for serving and the
 * untouched original beside it — because it becomes a property photo the
 * moment somebody uses it, and re-deriving from an already-compressed copy at
 * that point would lose quality for no reason.
 */
class MediaAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'media_collection_id',
        'disk',
        'path',
        'original_path',
        'original_name',
        'mime_type',
        'size_bytes',
        'width',
        'height',
        'sha256',
        'label',
        'alt_text',
        'uploaded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'width'      => 'integer',
            'height'     => 'integer',
        ];
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(MediaCollection::class, 'media_collection_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    /**
     * Served through the app, never linked directly.
     *
     * The bucket is private. Signed URLs were the alternative and they expire,
     * which makes them useless in an admin screen somebody leaves open.
     */
    public function displayUrl(): string
    {
        return route('admin.media.show', $this);
    }

    public function fileExists(): bool
    {
        return Storage::disk($this->disk)->exists($this->path);
    }

    /** What a screen reader announces once this lands on a listing. */
    public function altText(): string
    {
        return $this->alt_text ?: $this->label ?: 'Property photo';
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
}
