<?php

namespace App\Services\GeoIp;

/**
 * Immutable IP geolocation result. Returned from any GeoIpService::lookup().
 *
 * Every field is nullable — providers vary in coverage, and lookup failures
 * shouldn't block writes (FR-10.5).
 */
final readonly class GeoIpResult
{
    public function __construct(
        public ?string $country = null,    // ISO-3166 alpha-2
        public ?string $region = null,
        public ?string $city = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?int $asn = null,
        public ?string $asn_organization = null,
        public bool $is_vpn = false,
        public bool $is_tor = false,
        public bool $is_datacenter = false,
        public bool $is_anonymous = false,
    ) {
    }

    public static function empty(): self
    {
        return new self();
    }

    public function isResolved(): bool
    {
        return $this->country !== null || $this->city !== null;
    }

    public function toArray(): array
    {
        return [
            'country' => $this->country,
            'region' => $this->region,
            'city' => $this->city,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'asn' => $this->asn,
            'asn_organization' => $this->asn_organization,
            'is_vpn' => $this->is_vpn,
            'is_tor' => $this->is_tor,
            'is_datacenter' => $this->is_datacenter,
            'is_anonymous' => $this->is_anonymous,
        ];
    }
}
