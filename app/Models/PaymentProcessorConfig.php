<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-editable row per payment processor (table: payment_processors).
 *
 * Named ...Config to avoid colliding with the widely-imported
 * App\Enums\PaymentProcessor; `code` matches that enum's values exactly.
 * `credentials` is encrypted at rest via the encrypted:array cast and is
 * never serialized — toArray() replaces it with a redacted key list.
 */
class PaymentProcessorConfig extends Model
{
    public const REDACTED = '••••';

    protected $table = 'payment_processors';

    protected $fillable = [
        'code', 'display_name', 'enabled', 'mode', 'priority', 'credentials',
        'supports_connect', 'min_amount_cents', 'max_amount_cents',
        'currencies', 'is_default', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'priority' => 'integer',
            'credentials' => 'encrypted:array',
            'supports_connect' => 'boolean',
            'min_amount_cents' => 'integer',
            'max_amount_cents' => 'integer',
            'currencies' => 'array',
            'is_default' => 'boolean',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Cached routing gate: is this processor enabled in the admin console?
     * Missing row / unmigrated table fail OPEN so payments keep working on
     * environments that haven't seeded the config tables yet. The
     * 'processor.{code}' feature flag is checked by callers on top of this.
     */
    public static function isEnabled(string $code, bool $default = true): bool
    {
        try {
            $enabledMap = Cache::remember('payment_processors:enabled', 3600, fn () => static::query()
                ->pluck('enabled', 'code')
                ->map(fn ($enabled) => (bool) $enabled)
                ->all());
        } catch (QueryException) {
            return $default;
        }

        return $enabledMap[$code] ?? $default;
    }

    public static function bustCache(): void
    {
        Cache::forget('payment_processors:enabled');
    }

    /** Credential keys with every value masked — safe for UI/API display. */
    public function redactedCredentials(): array
    {
        $keys = array_keys($this->credentials ?? []);

        return array_fill_keys($keys, self::REDACTED);
    }

    public function toArray(): array
    {
        $array = parent::toArray();

        if (array_key_exists('credentials', $array)) {
            $array['credentials'] = $this->redactedCredentials();
        }

        return $array;
    }
}
