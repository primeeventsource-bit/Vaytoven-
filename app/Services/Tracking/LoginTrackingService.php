<?php

namespace App\Services\Tracking;

use App\Enums\Surface;
use App\Models\LoginSession;
use App\Models\User;
use App\Services\GeoIp\GeoIpService;
use Illuminate\Http\Request;

/**
 * Records authentication events into login_sessions (FR-1.6, FR-10.7).
 *
 * Every login/logout/failed/lockout call hits this service. Geo + threat-intel
 * enrichment happens inline via GeoIpService (NoOp in tests + envs without
 * MaxMind, real lookups in prod).
 *
 * Anomaly detection flags (FR-10.8) — new country, new device, geo-impossible,
 * Tor exit, datacenter ASN — are computed by AnomalyDetector (Phase 8).
 * For now, this service stores the raw fields and leaves is_suspicious=false.
 */
class LoginTrackingService
{
    public function __construct(private readonly GeoIpService $geoIp)
    {
    }

    public function record(
        User $user,
        string $authEvent,
        Request $request,
        ?string $sessionId = null,
    ): LoginSession {
        $surface = $request->attributes->get('vaytoven_surface') ?? Surface::Web;
        if (! $surface instanceof Surface) {
            $surface = Surface::tryFrom((string) $surface) ?? Surface::Web;
        }

        $ip = $request->ip();
        $ua = $request->userAgent();
        $geo = $this->geoIp->lookup($ip);

        return LoginSession::create([
            'user_id' => $user->id,
            'auth_event' => $authEvent,
            'surface' => $surface->value,
            'session_id' => $sessionId,
            'ip_address' => $ip,
            'country' => $geo->country,
            'region' => $geo->region,
            'city' => $geo->city,
            'latitude' => $geo->latitude,
            'longitude' => $geo->longitude,
            'asn' => $geo->asn,
            'is_vpn' => $geo->is_vpn,
            'is_tor' => $geo->is_tor,
            'is_datacenter' => $geo->is_datacenter,
            'device_type' => $this->classifyDevice($ua),
            'os' => $this->extractOs($ua),
            'browser' => $this->extractBrowser($ua),
            'user_agent' => $ua ? mb_substr($ua, 0, 512) : null,
            'is_suspicious' => false,             // computed by AnomalyDetector in Phase 8
            'suspicious_reasons' => null,
            'occurred_at' => now(),
        ]);
    }

    private function classifyDevice(?string $ua): string
    {
        if (! $ua) {
            return 'unknown';
        }
        $lower = strtolower($ua);
        if (str_contains($lower, 'tablet') || str_contains($lower, 'ipad')) {
            return 'tablet';
        }
        if (str_contains($lower, 'mobile') || str_contains($lower, 'android') || str_contains($lower, 'iphone')) {
            return 'mobile';
        }
        return 'desktop';
    }

    private function extractOs(?string $ua): ?string
    {
        if (! $ua) {
            return null;
        }
        return match (true) {
            str_contains($ua, 'Windows')              => 'Windows',
            str_contains($ua, 'Mac OS X'),
            str_contains($ua, 'Macintosh')            => 'macOS',
            str_contains($ua, 'iPhone'),
            str_contains($ua, 'iPad'),
            str_contains($ua, 'iPod')                 => 'iOS',
            str_contains($ua, 'Android')              => 'Android',
            str_contains($ua, 'Linux')                => 'Linux',
            default                                   => null,
        };
    }

    private function extractBrowser(?string $ua): ?string
    {
        if (! $ua) {
            return null;
        }
        return match (true) {
            str_contains($ua, 'Edg/')                 => 'Edge',
            str_contains($ua, 'Chrome/')              => 'Chrome',
            str_contains($ua, 'Safari/')              => 'Safari',
            str_contains($ua, 'Firefox/')             => 'Firefox',
            default                                   => null,
        };
    }
}
