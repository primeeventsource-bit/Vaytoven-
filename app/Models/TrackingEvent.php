<?php

namespace App\Models;

use App\Enums\Surface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use RuntimeException;

class TrackingEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'event_uuid',
        'event_type',
        'actor_user_id',
        'visitor_id',
        'surface',
        'ip_address',
        // Resolved from the IP at write time. Also present in metadata.geo;
        // these exist so the engagement map can group by city in SQL rather
        // than pulling every row into PHP.
        'country',
        'region',
        'city',
        'latitude',
        'longitude',
        // Audit context. See the migration for why these are columns
        // rather than parsed out of user_agent on every read.
        'session_id',
        'device_type',
        'browser',
        'platform',
        'referrer_host',
        'path',
        'subject_type',
        'subject_reference',
        'result',
        'user_agent',
        'metadata',
        'parent_hash',
        'current_hash',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'surface' => Surface::class,
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (TrackingEvent $event): void {
            if (empty($event->event_uuid)) {
                $event->event_uuid = (string) Str::uuid();
            }
            if (empty($event->occurred_at)) {
                $event->occurred_at = now();
            }
            if (empty($event->parent_hash)) {
                $event->parent_hash = static::query()->latest('id')->value('current_hash') ?? str_repeat('0', 64);
            }
            $event->current_hash = static::computeHash($event);
        });

        // Append-only enforcement at the model layer (FR-10.1).
        // MySQL also has a trigger (defense-in-depth); SQLite tests rely on this hook.
        static::updating(function (): void {
            throw new RuntimeException('tracking_events is append-only — UPDATE not allowed');
        });
        static::deleting(function (): void {
            throw new RuntimeException('tracking_events is append-only — DELETE not allowed');
        });
    }

    /**
     * Hash chain (FR-10.2): current_hash = sha256(parent_hash || event_uuid || event_type || actor_id || metadata_json).
     * Tampering with any historical row breaks chain verification.
     */
    public static function computeHash(TrackingEvent $event): string
    {
        $payload = implode('|', [
            $event->parent_hash ?? str_repeat('0', 64),
            $event->event_uuid,
            $event->event_type,
            $event->actor_user_id ?? '',
            json_encode($event->metadata ?? [], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]);

        return hash('sha256', $payload);
    }

    /**
     * Verify the chain from row 1 to row N. Returns the id of the first
     * tampered row, or null if the chain is intact.
     */
    public static function verifyChain(): ?int
    {
        $expectedParent = str_repeat('0', 64);

        foreach (static::query()->orderBy('id')->cursor() as $row) {
            $expectedCurrent = static::computeHash($row);

            if ($row->parent_hash !== $expectedParent || $row->current_hash !== $expectedCurrent) {
                return $row->id;
            }

            $expectedParent = $row->current_hash;
        }

        return null;
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
