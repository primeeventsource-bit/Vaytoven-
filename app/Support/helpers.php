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

if (! function_exists('et')) {
    /**
     * Render a timestamp in the site's display timezone.
     *
     * Timestamps are STORED in UTC and always will be. Switching
     * config('app.timezone') to Eastern would have been one line, and would
     * have been wrong twice over: every existing row — 4,500 activity events,
     * every login, every contract acceptance and payment — was written as UTC
     * and would suddenly be read as Eastern, silently moving history four
     * hours later than it happened. And new rows would be written in local
     * time, leaving one column holding two conventions with nothing to tell
     * them apart. In a system whose job includes proving when somebody agreed
     * to something, that is not a trade worth making.
     *
     * So storage stays UTC and only the display converts. The offset is part
     * of the output by default, because a bare time on an audit screen invites
     * the question "in which timezone" and there should never be an answer
     * other than the one printed next to it.
     */
    function et(?DateTimeInterface $date, string $format = 'M j, Y g:ia', bool $withZone = true): string
    {
        if (! $date) {
            return '—';
        }

        $local = \Illuminate\Support\Carbon::instance($date)
            ->setTimezone(config('app.display_timezone', 'America/New_York'));

        // The zone label belongs on a TIME, not on a date. "Aug 19, 2026 EDT"
        // reads as noise; "Aug 19, 2026 3:42pm EDT" answers the question a
        // reader of an audit screen actually has.
        // Escaped literals first: a format like 'F j, Y \a\t H:i' contains an
        // "a" and a "t" that are text, not placeholders, and matching them
        // would put a timezone on a date-only string.
        $placeholders = preg_replace('/\\./', '', $format);
        $hasTime = strpbrk($placeholders, 'gGhHisAa') !== false;

        // T renders EDT or EST, so the label follows daylight saving rather
        // than claiming one of them all year round.
        return $local->format($withZone && $hasTime ? $format.' T' : $format);
    }
}
