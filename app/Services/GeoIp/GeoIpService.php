<?php

namespace App\Services\GeoIp;

interface GeoIpService
{
    /**
     * Resolve an IP address to its geo + threat-intel data.
     *
     * MUST NOT throw on lookup failure — return GeoIpResult::empty() instead.
     * Auth + tracking writes shouldn't be blocked by a flaky GeoIP database
     * (FR-10.5).
     */
    public function lookup(?string $ipAddress): GeoIpResult;
}
