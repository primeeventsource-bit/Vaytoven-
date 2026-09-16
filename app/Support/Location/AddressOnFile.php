<?php

namespace App\Support\Location;

use App\Models\User;

/**
 * The member's address on file, as a value.
 *
 * Kept distinct from any IP location by type: nothing that holds a GeoIP
 * result can be passed where this is expected, and vice versa.
 */
final readonly class AddressOnFile
{
    public function __construct(
        public ?string $line1 = null,
        public ?string $line2 = null,
        public ?string $city = null,
        public ?string $state = null,
        public ?string $postalCode = null,
        public ?string $country = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
    ) {
    }

    public static function fromUser(User $user): self
    {
        return new self(
            line1:      $user->address_line1,
            line2:      $user->address_line2,
            city:       $user->address_city,
            state:      $user->address_state,
            postalCode: $user->address_postal_code,
            country:    $user->address_country,
            latitude:   $user->address_latitude !== null ? (float) $user->address_latitude : null,
            longitude:  $user->address_longitude !== null ? (float) $user->address_longitude : null,
        );
    }

    /** @param array<string, mixed>|null $data  as produced by toArray() */
    public static function fromArray(?array $data): self
    {
        $data ??= [];

        return new self(
            line1:      $data['line1'] ?? null,
            line2:      $data['line2'] ?? null,
            city:       $data['city'] ?? null,
            state:      $data['state'] ?? null,
            postalCode: $data['postal_code'] ?? null,
            country:    $data['country'] ?? null,
            latitude:   isset($data['latitude']) ? (float) $data['latitude'] : null,
            longitude:  isset($data['longitude']) ? (float) $data['longitude'] : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'line1'       => $this->line1,
            'line2'       => $this->line2,
            'city'        => $this->city,
            'state'       => $this->state,
            'postal_code' => $this->postalCode,
            'country'     => $this->country,
            'latitude'    => $this->latitude,
            'longitude'   => $this->longitude,
        ];
    }

    /** Enough to say what area the member lives in. */
    public function isKnown(): bool
    {
        return filled($this->city) || filled($this->postalCode) || filled($this->state);
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /** Street lines, then "City, ST 33101", then the country name. */
    public function lines(): array
    {
        $locality = trim(collect([$this->city])->filter()->implode('')
            .($this->state ? ($this->city ? ', ' : '').$this->state : '')
            .($this->postalCode ? ' '.$this->postalCode : ''));

        return array_values(array_filter([
            $this->line1,
            $this->line2,
            $locality ?: null,
            Regions::countryName($this->country),
        ]));
    }

    public function oneLine(): ?string
    {
        return $this->isKnown() || filled($this->line1) ? implode(', ', $this->lines()) : null;
    }

    /** "Miami, FL 33101" — the area, without the street. */
    public function area(): ?string
    {
        $area = trim(($this->city ?? '').($this->state ? ($this->city ? ', ' : '').$this->state : '').($this->postalCode ? ' '.$this->postalCode : ''));

        return $area !== '' ? $area : null;
    }
}
