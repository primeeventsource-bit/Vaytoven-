<?php

namespace App\Services\Tracking;

use App\Enums\Surface;
use App\Models\LoginSession;
use App\Models\User;
use App\Services\GeoIp\GeoIpService;
use Carbon\CarbonImmutable;
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
    public function __construct(
        private readonly GeoIpService $geoIp,
        private readonly AnomalyDetector $anomalyDetector,
    ) {
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

        // Anomaly detection (FR-10.8). Only applies to successful logins —
        // failed-attempt anomalies follow a different path (rate limiting,
        // not user-side surfacing).
        $reasons = ($authEvent === 'login')
            ? $this->anomalyDetector->detect($user, $geo, $ua, CarbonImmutable::now())
            : [];

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
            // One parser for every evidence surface, so login_sessions and the
            // activity log can never describe the same sign-in differently.
            'device_type' => ActivityRecorder::deviceType($ua),
            'os' => $ua ? ActivityRecorder::platform($ua) : null,
            'browser' => $ua ? ActivityRecorder::browser($ua) : null,
            'user_agent' => $ua ? mb_substr($ua, 0, 512) : null,
            'is_suspicious' => count($reasons) > 0,
            'suspicious_reasons' => $reasons ?: null,
            'occurred_at' => now(),
        ]);
    }
}
