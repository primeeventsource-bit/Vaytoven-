<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MemberDocument extends Model
{
    /** @var array<string, string> */
    public const CATEGORIES = [
        'advertising_agreement' => 'Advertising agreement',
        'member_agreement'      => 'Member services agreement',
        'property_document'     => 'Property document',
        'information_sheet'    => 'Property information sheet',
        'activation_certificate' => 'Activation certificate',
        'invoice'               => 'Invoice',
        'receipt'               => 'Receipt',
        'identification'        => 'Supporting document',
        'other'                 => 'Other',
    ];

    protected $fillable = [
        'user_id', 'property_id', 'category', 'title', 'disk', 'path',
        'original_name', 'mime_type', 'size_bytes', 'sha256',
        'uploaded_by_user_id', 'notes',
    ];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The property this document is about, when it is about one.
     *
     * Null means it belongs to the member rather than to any single listing —
     * a member agreement is not a property document, and pretending otherwise
     * would misfile it.
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function scopeForProperty($query, int $propertyId)
    {
        return $query->where('property_id', $propertyId);
    }

    /** Member-level documents only: everything not filed against a listing. */
    public function scopeMemberLevel($query)
    {
        return $query->whereNull('property_id');
    }
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst(str_replace('_', ' ', $this->category));
    }

    public function sizeForHumans(): string
    {
        $bytes = $this->size_bytes;

        return match (true) {
            $bytes >= 1048576 => number_format($bytes / 1048576, 1).' MB',
            $bytes >= 1024    => number_format($bytes / 1024, 0).' KB',
            default           => $bytes.' B',
        };
    }

    /**
     * Is the file still where the row says it is?
     *
     * A row whose file has gone is worse than no row: the list shows a
     * document that looks available and fails on download, which is exactly
     * how a member of staff discovers a problem at the moment they need the
     * file for a dispute.
     */
    public function fileExists(): bool
    {
        return Storage::disk($this->disk)->exists($this->path);
    }
}
