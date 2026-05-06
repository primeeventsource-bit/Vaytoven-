<?php

namespace App\Services\GeoIp;

use GeoIp2\Database\Reader;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * MaxMind GeoIP2 provider (FR-10.5). Reads from a local .mmdb file.
 *
 * License-required. Production deployments mount the .mmdb file via the
 * Laravel Cloud filesystem; the path is set via env MAXMIND_MMDB_PATH.
 *
 * VPN/Tor/datacenter detection requires the MaxMind GeoIP2 Anonymous IP
 * database (separate file). If only the City database is available we
 * still return country/region/city/lat/lng but the threat-intel flags
 * stay false.
 */
class MaxMindGeoIpService implements GeoIpService
{
    public function __construct(
        private readonly ?string $cityDbPath = null,
        private readonly ?string $anonymousDbPath = null,
    ) {
    }

    public function lookup(?string $ipAddress): GeoIpResult
    {
        if (! $ipAddress) {
            return GeoIpResult::empty();
        }

        $country = null;
        $region = null;
        $city = null;
        $latitude = null;
        $longitude = null;
        $asn = null;
        $asnOrg = null;

        if ($this->cityDbPath && is_readable($this->cityDbPath)) {
            try {
                $reader = new Reader($this->cityDbPath);
                $record = $reader->city($ipAddress);

                $country = $record->country->isoCode;
                $region = $record->mostSpecificSubdivision->name ?? null;
                $city = $record->city->name ?? null;
                $latitude = $record->location->latitude;
                $longitude = $record->location->longitude;

                $reader->close();
            } catch (Throwable $e) {
                Log::warning("maxmind city lookup failed for {$ipAddress}: ".$e->getMessage());
            }
        }

        $isVpn = false;
        $isTor = false;
        $isDatacenter = false;
        $isAnon = false;

        if ($this->anonymousDbPath && is_readable($this->anonymousDbPath)) {
            try {
                $reader = new Reader($this->anonymousDbPath);
                $record = $reader->anonymousIp($ipAddress);

                $isVpn = (bool) ($record->isAnonymousVpn ?? false);
                $isTor = (bool) ($record->isTorExitNode ?? false);
                $isDatacenter = (bool) ($record->isHostingProvider ?? false);
                $isAnon = (bool) ($record->isAnonymous ?? false);

                $reader->close();
            } catch (Throwable $e) {
                Log::warning("maxmind anon lookup failed for {$ipAddress}: ".$e->getMessage());
            }
        }

        return new GeoIpResult(
            country: $country,
            region: $region,
            city: $city,
            latitude: $latitude,
            longitude: $longitude,
            asn: $asn,
            asn_organization: $asnOrg,
            is_vpn: $isVpn,
            is_tor: $isTor,
            is_datacenter: $isDatacenter,
            is_anonymous: $isAnon,
        );
    }
}
