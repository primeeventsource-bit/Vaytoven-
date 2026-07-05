<?php

namespace App\Services\Settings;

use App\Models\Setting;
use App\Models\User;
use App\Services\AdminAuditLogService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Cache-through access to the settings table (Layer 1).
 *
 * Reads go Cache -> DB -> SettingsSchema default, so setting() is safe to
 * call before the table exists (fresh deploys, early middleware). Writes are
 * transactional, validated against the SettingsSchema allow-list, audited
 * via AdminAuditLogService (sensitive values redacted), and bust the cache
 * immediately — correctness never rests on TTL expiry.
 *
 * The cache stores the raw serialized column value (ciphertext for
 * encrypted settings), never decrypted plaintext.
 */
class SettingsRepository
{
    /** Belt-and-braces TTL; writes invalidate explicitly. */
    private const TTL_SECONDS = 3600;

    private const MISSING = '__missing__';

    public const PUBLIC_CACHE_KEY = 'settings:public';

    public function get(string $key, mixed $default = null): mixed
    {
        $spec = SettingsSchema::spec($key);
        if ($spec === null) {
            return $default;
        }

        try {
            $raw = Cache::remember("settings:{$key}", self::TTL_SECONDS, function () use ($key) {
                $row = Setting::query()->where('key', $key)->first();

                return $row?->value ?? self::MISSING;
            });
        } catch (QueryException) {
            // Table not migrated yet (fresh environment) — schema default.
            $raw = self::MISSING;
        }

        if ($raw === self::MISSING || $raw === null) {
            return $default ?? $spec['default'];
        }

        return Setting::castFromStorage($raw, $spec['type']);
    }

    /**
     * Normalize + validate a candidate value without persisting.
     * Returns the normalized value ready for set().
     *
     * @throws ValidationException on unknown key or rule violation
     */
    public function validateValue(string $key, mixed $value): mixed
    {
        $spec = SettingsSchema::spec($key);
        if ($spec === null) {
            throw ValidationException::withMessages([$key => "Unknown setting key '{$key}'."]);
        }

        $value = $this->normalize($value, $spec['type']);

        // Validate under a fixed field name — dotted setting keys would be
        // misread as nested arrays by the validator.
        $validator = Validator::make(
            ['value' => $value],
            ['value' => $spec['rules']],
            [],
            ['value' => $spec['label']],
        );

        if ($validator->fails()) {
            throw ValidationException::withMessages([
                $key => $validator->errors()->all(),
            ]);
        }

        return $value;
    }

    /**
     * Validate + persist one setting, audit the change, bust caches.
     *
     * @throws ValidationException on unknown key or rule violation
     */
    public function set(string $key, mixed $value, User $actor, ?string $ipAddress = null): Setting
    {
        $spec = SettingsSchema::spec($key);
        $value = $this->validateValue($key, $value);

        $setting = DB::transaction(function () use ($key, $value, $spec, $actor, $ipAddress) {
            $setting = Setting::query()->where('key', $key)->lockForUpdate()->first()
                ?? new Setting(self::rowAttributes($key, $spec));

            $oldRaw = $setting->exists ? $setting->value : null;
            $newRaw = Setting::castToStorage($value, $spec['type']);

            $setting->value = $newRaw;
            $setting->updated_by = $actor->id;
            $setting->save();

            AdminAuditLogService::log(
                actor: $actor,
                action: 'setting.update',
                subject: $setting,
                payload: [
                    'key' => $key,
                    'old_value' => $this->auditValue($oldRaw, $spec),
                    'new_value' => $this->auditValue($newRaw, $spec),
                ],
                ipAddress: $ipAddress,
            );

            return $setting;
        });

        $this->forget($key);

        return $setting;
    }

    /** All settings of one group as schema-merged rows for the admin UI. */
    public function groupForDisplay(string $group): array
    {
        $rows = Setting::query()->where('group', $group)->get()->keyBy('key');

        $out = [];
        foreach (SettingsSchema::group($group) as $key => $spec) {
            $row = $rows->get($key);
            $hasValue = $row !== null && ($row->value ?? '') !== '';
            $typed = $row?->typed_value ?? $spec['default'];

            $out[$key] = [
                'key' => $key,
                'type' => $spec['type'],
                'label' => $spec['label'],
                'help' => $spec['help'],
                'options' => $spec['options'],
                'sensitive' => $spec['sensitive'],
                'public' => $spec['public'],
                'value' => $spec['sensitive'] ? ($hasValue ? Setting::REDACTED : '') : $typed,
                'has_value' => $hasValue,
                'default' => $spec['sensitive'] ? null : $spec['default'],
            ];
        }

        return $out;
    }

    /** Cached bundle of public settings for the frontend bootstrap. */
    public function allPublic(): array
    {
        return Cache::remember(self::PUBLIC_CACHE_KEY, self::TTL_SECONDS, function () {
            $rows = Setting::query()->where('is_public', true)->get()->keyBy('key');

            $out = [];
            foreach (SettingsSchema::catalog() as $key => $spec) {
                if (! $spec['public'] || $spec['sensitive']) {
                    continue;
                }
                $row = $rows->get($key);
                $out[$key] = $row?->typed_value ?? $spec['default'];
            }

            return $out;
        });
    }

    public function forget(string $key): void
    {
        Cache::forget("settings:{$key}");
        Cache::forget(self::PUBLIC_CACHE_KEY);
    }

    /** Coerce request input to the PHP shape the type expects. */
    private function normalize(mixed $value, string $type): mixed
    {
        return match ($type) {
            'int', 'cents', 'percent' => is_numeric($value) ? (int) $value : $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => is_string($value) && $value !== '' ? (json_decode($value, true) ?? $value) : ($value ?: []),
            default => $value === null ? '' : (string) $value,
        };
    }

    private function auditValue(?string $raw, array $spec): ?string
    {
        if ($spec['sensitive']) {
            return ($raw ?? '') === '' ? null : Setting::REDACTED;
        }

        return $raw;
    }

    private static function rowAttributes(string $key, array $spec): array
    {
        return [
            'group' => $spec['group'],
            'key' => $key,
            'type' => $spec['type'],
            'default_value' => Setting::castToStorage($spec['default'], $spec['type'] === 'encrypted' ? 'string' : $spec['type']),
            'enum_options' => $spec['options'],
            'is_public' => $spec['public'],
            'is_sensitive' => $spec['sensitive'],
            'label' => $spec['label'],
            'help_text' => $spec['help'],
            'sort_order' => $spec['sort'],
        ];
    }
}
