<?php

namespace App\Support\Location;

/**
 * Compares the member's address on file with an approximate IP location.
 *
 * The result describes AREAS, never a person's whereabouts. GeoIP is shaped by
 * mobile carrier routing, VPNs, corporate networks, ISP routing and privacy
 * services; a match is consistent with the member being in their area and
 * proves nothing about where they physically were. Every sentence this class
 * produces is worded that way, and nothing should restate it more strongly.
 *
 * It refuses to guess. Without a measurable distance, two different city names
 * in the same state are LOCATION UNAVAILABLE — calling Miami Beach "a different
 * area" from Miami would be as wrong as calling Tallahassee nearby.
 */
final readonly class LocationComparison
{
    public const CONSISTENT  = 'consistent';
    public const NEARBY      = 'nearby';
    public const DIFFERENT   = 'different';
    public const UNAVAILABLE = 'unavailable';

    /** Distance thresholds, in miles, between the geocoded address and the IP location. */
    public const CONSISTENT_WITHIN_MILES = 25;
    public const NEARBY_WITHIN_MILES     = 75;

    public const DISCLOSURE = 'IP-based geolocation is approximate and does not establish the individual\'s precise physical location.';

    public function __construct(
        public string $status,
        public ?float $distanceMiles,
        public string $reason,
    ) {
    }

    public static function compare(
        AddressOnFile $address,
        ?string $ipCity,
        ?string $ipRegion,
        ?string $ipCountry,
        ?float $ipLatitude = null,
        ?float $ipLongitude = null,
    ): self {
        if (! $address->isKnown()) {
            return new self(self::UNAVAILABLE, null, 'No address on file.');
        }

        if (blank($ipCity) && blank($ipRegion)) {
            return new self(self::UNAVAILABLE, null, blank($ipCountry)
                ? 'The IP address could not be located.'
                : 'Only a country-level IP location is available.');
        }

        $addrCountry = Regions::country($address->country) ?? 'US';
        $ipCountryN  = Regions::country($ipCountry);

        if ($ipCountryN !== null && $ipCountryN !== $addrCountry) {
            return new self(self::DIFFERENT, null, 'The approximate IP location is in a different country from the address on file.');
        }

        $sameCity  = Regions::city($address->city) !== null && Regions::city($address->city) === Regions::city($ipCity);
        $addrState = Regions::state($address->state);
        $ipState   = Regions::state($ipRegion);
        $sameState = $addrState !== null && $addrState === $ipState;

        if ($address->hasCoordinates() && $ipLatitude !== null && $ipLongitude !== null) {
            $miles = round(Regions::miles($address->latitude, $address->longitude, $ipLatitude, $ipLongitude), 1);

            if ($miles <= self::CONSISTENT_WITHIN_MILES || ($sameCity && ($sameState || $ipState === null))) {
                return new self(self::CONSISTENT, $miles, "Approximately {$miles} miles from the address on file.");
            }

            if ($miles <= self::NEARBY_WITHIN_MILES) {
                return new self(self::NEARBY, $miles, "Approximately {$miles} miles from the address on file.");
            }

            return new self(self::DIFFERENT, $miles, "Approximately {$miles} miles from the address on file.");
        }

        // No distance available: only what the names can support.
        if ($sameCity && ($sameState || $addrState === null || $ipState === null)) {
            return new self(self::CONSISTENT, null, 'Same city as the address on file (distance not measured).');
        }

        if ($addrState !== null && $ipState !== null && ! $sameState) {
            return new self(self::DIFFERENT, null, 'Different state from the address on file (distance not measured).');
        }

        return new self(self::UNAVAILABLE, null, 'Different city name and no measurable distance; the areas could not be compared.');
    }

    public function label(): string
    {
        return self::labelFor($this->status);
    }

    public static function labelFor(?string $status): string
    {
        return match ($status) {
            self::CONSISTENT => 'CONSISTENT WITH ADDRESS AREA',
            self::NEARBY     => 'SAME METRO / NEARBY AREA',
            self::DIFFERENT  => 'DIFFERENT AREA',
            default          => 'LOCATION UNAVAILABLE',
        };
    }

    /** The only sentence form this result should be stated in. */
    public static function sentenceFor(?string $status): string
    {
        return match ($status) {
            self::CONSISTENT => 'Approximate IP location is consistent with the member\'s address area.',
            self::NEARBY     => 'Approximate IP location is in the same metro or a nearby area to the member\'s address.',
            self::DIFFERENT  => 'Approximate IP location is in a different area from the member\'s address.',
            default          => 'Approximate IP location could not be compared with the member\'s address.',
        };
    }

    /** @return array{status: string, label: string, distance_miles: ?float, reason: string} */
    public function toArray(): array
    {
        return [
            'status'         => $this->status,
            'label'          => $this->label(),
            'distance_miles' => $this->distanceMiles,
            'reason'         => $this->reason,
        ];
    }
}
