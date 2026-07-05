<?php

use App\Models\User;
use App\Services\Settings\FeatureFlagService;
use App\Services\Settings\SettingsRepository;

if (! function_exists('setting')) {
    /**
     * Read an admin-tunable setting (cache -> DB -> SettingsSchema default).
     *
     *   setting('fees.guest_service_pct', 12)   // int percent
     *   setting('general.maintenance_mode')     // bool
     *
     * Unknown keys return $default — business code should only ever pass
     * keys defined in SettingsSchema.
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return app(SettingsRepository::class)->get($key, $default);
    }
}

if (! function_exists('feature')) {
    /**
     * Resolve a feature flag, honoring scope + rollout percentage.
     * Missing flag rows resolve to $default (fail-open for existing
     * functionality on environments where the seeder hasn't run).
     */
    function feature(string $key, ?User $user = null, bool $default = true): bool
    {
        $user ??= auth()->user();

        return app(FeatureFlagService::class)->enabled($key, $user, $default);
    }
}
