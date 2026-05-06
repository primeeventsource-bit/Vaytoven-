<?php

namespace App\Services\Tracking;

use App\Models\LoginSession;
use App\Models\User;
use App\Services\GeoIp\GeoIpResult;
use Carbon\CarbonImmutable;

/**
 * Detects suspicious login signals (FR-10.8) by comparing the current
 * login attempt against the user's recent history.
 *
 * Returns a list of reason strings — every reason that fires goes into
 * login_sessions.suspicious_reasons. Empty list = clean login.
 *
 * Detection rules:
 *   • new_country — country never seen in the user's last 30 successful logins
 *   • new_device  — user-agent fingerprint never seen in the last 30 logins
 *   • geo_impossibility — last login > 1000 km away AND less than 1 hour ago
 *   • known_tor — current login is from a Tor exit node
 *   • datacenter — current login from a hosting/datacenter IP (rare for real users)
 */
class AnomalyDetector
{
    private const HISTORY_WINDOW = 30;            // last N logins to compare against
    private const IMPOSSIBLE_DISTANCE_KM = 1000;  // physically impossible threshold
    private const IMPOSSIBLE_HOURS = 1;           // within this window

    /**
     * @return array<string> list of reason strings (subset of:
     *  new_country, new_device, geo_impossibility, known_tor, datacenter)
     */
    public function detect(
        User $user,
        GeoIpResult $geo,
        ?string $userAgent,
        ?CarbonImmutable $now = null,
    ): array {
        $now ??= CarbonImmutable::now();
        $reasons = [];

        if ($geo->is_tor) {
            $reasons[] = 'known_tor';
        }

        if ($geo->is_datacenter) {
            $reasons[] = 'datacenter';
        }

        $history = LoginSession::query()
            ->where('user_id', $user->id)
            ->where('auth_event', 'login')
            ->orderByDesc('occurred_at')
            ->limit(self::HISTORY_WINDOW)
            ->get();

        // First-ever login: no history to compare against, no anomaly.
        if ($history->isEmpty()) {
            return $reasons;
        }

        // new_country: current country missing from history.
        if ($geo->country && ! $history->pluck('country')->filter()->contains($geo->country)) {
            $reasons[] = 'new_country';
        }

        // new_device: UA fingerprint missing from history.
        if ($userAgent) {
            $fingerprint = $this->fingerprint($userAgent);
            $seen = $history->pluck('user_agent')->filter()->map(fn ($ua) => $this->fingerprint($ua));
            if (! $seen->contains($fingerprint)) {
                $reasons[] = 'new_device';
            }
        }

        // geo_impossibility: previous login physically too far away in too little time.
        $previous = $history->first();
        if ($previous
            && $previous->latitude !== null && $previous->longitude !== null
            && $geo->latitude !== null && $geo->longitude !== null
        ) {
            $distance = $this->haversineKm(
                (float) $previous->latitude,
                (float) $previous->longitude,
                $geo->latitude,
                $geo->longitude,
            );
            $hoursSince = $now->diffInMinutes($previous->occurred_at, absolute: true) / 60.0;

            if ($distance > self::IMPOSSIBLE_DISTANCE_KM && $hoursSince < self::IMPOSSIBLE_HOURS) {
                $reasons[] = 'geo_impossibility';
            }
        }

        return $reasons;
    }

    /**
     * Coarse UA fingerprint — strip version numbers so a Chrome upgrade
     * doesn't trip new_device on every release. Browser + OS family is enough.
     */
    private function fingerprint(string $userAgent): string
    {
        // Drop digits + dots, lowercase, trim whitespace.
        return preg_replace('/[\d.]+/', '', strtolower(trim($userAgent))) ?? '';
    }

    /**
     * Great-circle distance in km between two lat/lng points.
     */
    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return 2 * $earthKm * asin(min(1.0, sqrt($a)));
    }
}
