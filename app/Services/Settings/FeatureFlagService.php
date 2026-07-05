<?php

namespace App\Services\Settings;

use App\Models\FeatureFlag;
use App\Models\User;
use App\Services\AdminAuditLogService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Feature flag resolution (Layer 3), honoring scope and rollout_pct.
 *
 * A missing flag row resolves to $default (true unless the caller says
 * otherwise) so gating existing functionality fails OPEN on environments
 * where the seeder hasn't run yet — a flag that doesn't exist should never
 * dark-ship a feature that used to work.
 */
class FeatureFlagService
{
    private const CACHE_KEY = 'feature_flags:all';

    private const TTL_SECONDS = 3600;

    public function enabled(string $key, ?User $user = null, bool $default = true): bool
    {
        $flag = $this->all()->get($key);
        if ($flag === null) {
            return $default;
        }

        if (! $flag->enabled) {
            return false;
        }

        if (! $this->scopeMatches($flag, $user)) {
            return false;
        }

        return $this->inRollout($flag, $user);
    }

    /** @return Collection<string, FeatureFlag> keyed by flag key */
    public function all(): Collection
    {
        try {
            return Cache::remember(self::CACHE_KEY, self::TTL_SECONDS, fn () => FeatureFlag::query()->orderBy('key')->get())
                ->keyBy('key');
        } catch (QueryException) {
            return collect(); // table not migrated yet
        }
    }

    /** Toggle/update a flag, audit it, bust the cache. */
    public function update(string $key, array $attributes, User $actor, ?string $ipAddress = null): FeatureFlag
    {
        $flag = FeatureFlag::query()->where('key', $key)->firstOrFail();
        $before = $flag->only(['enabled', 'scope', 'scope_value', 'rollout_pct']);

        $flag->fill($attributes);
        $flag->updated_by = $actor->id;
        $flag->save();

        AdminAuditLogService::log(
            actor: $actor,
            action: 'flag.toggle',
            subject: $flag,
            payload: [
                'key' => $key,
                'old_value' => $before,
                'new_value' => $flag->only(['enabled', 'scope', 'scope_value', 'rollout_pct']),
            ],
            ipAddress: $ipAddress,
        );

        $this->bust();

        return $flag;
    }

    public function bust(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(SettingsRepository::PUBLIC_CACHE_KEY);
    }

    /** Resolved on/off map for the public bootstrap payload (anonymous visitor). */
    public function publicStates(): array
    {
        $out = [];
        foreach ($this->all() as $key => $flag) {
            // Processor gates are operational detail, not frontend bootstrap.
            if (str_starts_with($key, 'processor.')) {
                continue;
            }
            $out[$key] = $this->enabled($key);
        }

        return $out;
    }

    private function scopeMatches(FeatureFlag $flag, ?User $user): bool
    {
        return match ($flag->scope) {
            'role', 'audience' => $this->roleMatches($flag->scope_value, $user),
            'environment' => app()->environment((string) $flag->scope_value),
            default => true, // global
        };
    }

    private function roleMatches(?string $scopeValue, ?User $user): bool
    {
        if ($scopeValue === null || $scopeValue === '') {
            return true;
        }

        // Anonymous visitors count as travelers (guests browsing).
        $role = $user?->role?->value ?? 'traveler';

        return $role === $scopeValue;
    }

    private function inRollout(FeatureFlag $flag, ?User $user): bool
    {
        $pct = $flag->rollout_pct;
        if ($pct === null || $pct >= 100) {
            return true;
        }
        if ($pct <= 0) {
            return false;
        }

        // Deterministic bucket per (flag, subject): same user always gets the
        // same answer while the percentage holds.
        $subject = $user?->id ?? request()?->ip() ?? 'cli';

        return (crc32($flag->key.'|'.$subject) % 100) < $pct;
    }
}
