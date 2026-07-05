<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

/**
 * One row per admin-tunable setting (Layer 1 of the configuration system).
 *
 * The raw `value` column is a serialized string; use typed_value to get the
 * correctly-cast PHP value for the row's `type`. `encrypted` values are
 * encrypted at rest and decrypted only through typed_value. Sensitive rows
 * never expose their value through toArray() — they serialize as '••••'.
 */
class Setting extends Model
{
    public const REDACTED = '••••';

    public const TYPES = ['string', 'text', 'int', 'cents', 'percent', 'bool', 'json', 'encrypted', 'enum'];

    protected $fillable = [
        'group', 'key', 'type', 'value', 'default_value', 'enum_options',
        'is_public', 'is_sensitive', 'label', 'help_text', 'sort_order', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'enum_options' => 'array',
            'is_public' => 'boolean',
            'is_sensitive' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** The correctly-cast PHP value (falls back to default_value when unset). */
    public function getTypedValueAttribute(): mixed
    {
        $raw = $this->value ?? $this->default_value;

        return static::castFromStorage($raw, $this->type);
    }

    /** Serialize a PHP value into the string form stored in `value`. */
    public static function castToStorage(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'int', 'cents', 'percent' => (string) (int) $value,
            'bool' => $value ? '1' : '0',
            'json' => is_string($value) ? $value : json_encode($value),
            'encrypted' => Crypt::encryptString((string) $value),
            default => (string) $value,
        };
    }

    /** Cast a stored string back to its PHP value. */
    public static function castFromStorage(?string $raw, string $type): mixed
    {
        if ($raw === null) {
            return null;
        }

        return match ($type) {
            'int', 'cents', 'percent' => (int) $raw,
            'bool' => $raw === '1' || $raw === 'true',
            'json' => json_decode($raw, true),
            'encrypted' => self::decryptQuietly($raw),
            default => $raw,
        };
    }

    /**
     * Encrypted defaults are seeded as plaintext '' (nothing secret); only
     * operator-written values are ciphertext. Tolerate both.
     */
    private static function decryptQuietly(string $raw): string
    {
        if ($raw === '') {
            return '';
        }

        try {
            return Crypt::decryptString($raw);
        } catch (DecryptException) {
            return $raw;
        }
    }

    /** Never leak sensitive/encrypted values through serialization. */
    public function toArray(): array
    {
        $array = parent::toArray();

        if ($this->is_sensitive || $this->type === 'encrypted') {
            $hasValue = ($this->value ?? '') !== '';
            $array['value'] = $hasValue ? self::REDACTED : null;
            $array['default_value'] = null;
        }

        return $array;
    }
}
