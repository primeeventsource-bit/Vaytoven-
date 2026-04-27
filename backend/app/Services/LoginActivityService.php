<?php

namespace App\Services;

use App\Models\LoginActivity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LoginActivityService
{
    public function record(User $user, Request $request, string $event): LoginActivity
    {
        $ip = $request->ip();
        $location = $this->lookupIp($ip);
        $ua = $this->parseUserAgent($request->userAgent() ?? '');

        $suspicious = $this->detectSuspicious($user, $location, $ua, $event);

        return LoginActivity::create([
            'user_id'             => $user->id,
            'ip_address'          => $ip,
            'country_code'        => $location['country_code'] ?? null,
            'region'              => $location['region']       ?? null,
            'city'                => $location['city']         ?? null,
            'latitude'            => $location['latitude']     ?? null,
            'longitude'           => $location['longitude']    ?? null,
            'user_agent'          => $request->userAgent(),
            'device_type'         => $ua['device_type'] ?? null,
            'os'                  => $ua['os']          ?? null,
            'browser'             => $ua['browser']     ?? null,
            'event'               => $event,
            'was_suspicious'      => $suspicious !== [],
            'suspicious_reasons'  => $suspicious !== [] ? $suspicious : null,
            'created_at'          => now(),
        ]);
    }

    /**
     * Look up an IP via MaxMind GeoLite2.
     * Cached locally for 24h to keep lookup overhead low.
     */
    private function lookupIp(string $ip): array
    {
        return Cache::remember("ip_geo:$ip", now()->addHours(24), function () use ($ip) {
            $dbPath = config('services.maxmind.db_path');
            if (! $dbPath || ! is_file($dbPath)) {
                return [];
            }

            try {
                $reader = new \GeoIp2\Database\Reader($dbPath);
                $record = $reader->city($ip);
                return [
                    'country_code' => $record->country->isoCode,
                    'region'       => $record->mostSpecificSubdivision->name,
                    'city'         => $record->city->name,
                    'latitude'     => $record->location->latitude,
                    'longitude'    => $record->location->longitude,
                ];
            } catch (\Throwable) {
                return [];
            }
        });
    }

    /**
     * Cheap UA sniff. For a real launch you'd use jenssegers/agent or similar.
     */
    private function parseUserAgent(string $ua): array
    {
        $deviceType = match (true) {
            str_contains($ua, 'iPhone') || str_contains($ua, 'Android') => 'mobile',
            str_contains($ua, 'iPad') || str_contains($ua, 'Tablet')    => 'tablet',
            $ua === ''                                                  => 'unknown',
            default                                                     => 'desktop',
        };

        $os = match (true) {
            str_contains($ua, 'Windows')   => 'Windows',
            str_contains($ua, 'Mac OS X')  => 'macOS',
            str_contains($ua, 'Android')   => 'Android',
            str_contains($ua, 'iPhone OS'),
            str_contains($ua, 'iPad'),
            str_contains($ua, 'iOS')       => 'iOS',
            str_contains($ua, 'Linux')     => 'Linux',
            default                        => null,
        };

        $browser = match (true) {
            str_contains($ua, 'Edg/')     => 'Edge',
            str_contains($ua, 'Chrome/')  => 'Chrome',
            str_contains($ua, 'Safari/')  => 'Safari',
            str_contains($ua, 'Firefox/') => 'Firefox',
            default                       => null,
        };

        return compact('deviceType', 'os', 'browser') + ['device_type' => $deviceType];
    }

    /**
     * Run a few cheap heuristics for anomaly detection.
     * Real implementation belongs in a fraud-rules service or a 3rd-party signal vendor.
     */
    private function detectSuspicious(User $user, array $location, array $ua, string $event): array
    {
        $reasons = [];

        if ($event === 'login_failed') {
            $reasons[] = 'failed_attempt';
        }

        // New country compared to last successful login?
        $lastSuccess = LoginActivity::where('user_id', $user->id)
            ->where('event', 'login_success')
            ->latest('created_at')
            ->first();

        if ($lastSuccess
            && isset($location['country_code'], $lastSuccess->country_code)
            && $lastSuccess->country_code !== $location['country_code']
            && $lastSuccess->created_at->diffInHours(now()) < 6
        ) {
            $reasons[] = 'impossible_travel';
        }

        // Many failed logins in the last 15 minutes
        $recentFailures = LoginActivity::where('user_id', $user->id)
            ->where('event', 'login_failed')
            ->where('created_at', '>', now()->subMinutes(15))
            ->count();

        if ($recentFailures >= 5) {
            $reasons[] = 'brute_force_attempts';
        }

        return $reasons;
    }
}
