<?php

namespace App\Services\GeoIp;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Decorator that caches lookups in Redis with a 7-day TTL keyed by IP (FR-10.5).
 *
 * The cache MUST swallow upstream lookup failures — empty results are also
 * cached (negative cache) for a shorter window so a transient failure doesn't
 * pin a "no data" response for a week. Negative cache TTL: 1 hour.
 */
class CachedGeoIpService implements GeoIpService
{
    private const POSITIVE_TTL_SECONDS = 7 * 24 * 60 * 60;  // 7 days
    private const NEGATIVE_TTL_SECONDS = 60 * 60;           // 1 hour

    public function __construct(
        private readonly GeoIpService $upstream,
        private readonly CacheRepository $cache,
    ) {
    }

    public function lookup(?string $ipAddress): GeoIpResult
    {
        if (! $ipAddress) {
            return GeoIpResult::empty();
        }

        $key = 'geoip:'.$ipAddress;
        $cached = $this->cache->get($key);

        if (is_array($cached)) {
            return $this->fromArray($cached);
        }

        $result = $this->upstream->lookup($ipAddress);

        $this->cache->put(
            $key,
            $result->toArray(),
            $result->isResolved() ? self::POSITIVE_TTL_SECONDS : self::NEGATIVE_TTL_SECONDS,
        );

        return $result;
    }

    private function fromArray(array $data): GeoIpResult
    {
        return new GeoIpResult(
            country: $data['country'] ?? null,
            region: $data['region'] ?? null,
            city: $data['city'] ?? null,
            latitude: isset($data['latitude']) ? (float) $data['latitude'] : null,
            longitude: isset($data['longitude']) ? (float) $data['longitude'] : null,
            asn: isset($data['asn']) ? (int) $data['asn'] : null,
            asn_organization: $data['asn_organization'] ?? null,
            is_vpn: (bool) ($data['is_vpn'] ?? false),
            is_tor: (bool) ($data['is_tor'] ?? false),
            is_datacenter: (bool) ($data['is_datacenter'] ?? false),
            is_anonymous: (bool) ($data['is_anonymous'] ?? false),
        );
    }
}
