<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertySnapshot extends Model
{
    /** Snapshots are written once. There is no updated_at. */
    public const UPDATED_AT = null;

    public const REASON_ACTIVATED = 'activated';
    public const REASON_EDITED    = 'edited';
    public const REASON_MANUAL    = 'manual';

    protected $fillable = [
        'property_id', 'reason', 'content', 'content_hash',
        'captured_by_user_id', 'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'content'     => 'array',
            'captured_at' => 'datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by_user_id');
    }

    public function reasonLabel(): string
    {
        return match ($this->reason) {
            self::REASON_ACTIVATED => 'Advertising activated',
            self::REASON_EDITED    => 'Listing edited',
            self::REASON_MANUAL    => 'Captured by staff',
            default                => ucfirst($this->reason),
        };
    }

    /**
     * Has the stored content been altered since capture?
     *
     * The whole value of a snapshot is that it is the ad as published, so the
     * ability to demonstrate it has not been edited afterwards is the point —
     * not a nicety. A snapshot that fails this is worse than none, because it
     * would otherwise be presented as evidence.
     */
    public function isIntact(): bool
    {
        return hash_equals(
            $this->content_hash,
            self::hashContent($this->content ?? []),
        );
    }

    /**
     * Canonical hash of a content array.
     *
     * Keys are sorted before encoding so the hash depends on the VALUES, not
     * on the order PHP happened to build the array in — otherwise an
     * unchanged snapshot could fail its own integrity check after a refactor.
     */
    public static function hashContent(array $content): string
    {
        ksort($content);

        return hash('sha256', json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
