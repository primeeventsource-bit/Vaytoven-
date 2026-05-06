<?php

namespace App\Services\GeoIp;

/**
 * Default provider when no GeoIP backend is configured. Always returns empty.
 * Used in tests and during local dev — production should bind to MaxMindGeoIpService.
 */
class NoOpGeoIpService implements GeoIpService
{
    public function lookup(?string $ipAddress): GeoIpResult
    {
        return GeoIpResult::empty();
    }
}
