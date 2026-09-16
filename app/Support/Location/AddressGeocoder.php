<?php

namespace App\Support\Location;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Turns an address on file into coordinates, once, when it is saved.
 *
 * US Census Bureau geocoder: public-domain results that may be stored, no API
 * key. Mapbox's standard terms forbid storing geocoding results, which is why
 * the map token is not used here. US addresses only; anything else stays
 * without coordinates and comparisons fall back to names.
 *
 * Never throws and never blocks a save: an address without coordinates is
 * still an address.
 */
class AddressGeocoder
{
    private const ENDPOINT = 'https://geocoding.geo.census.gov/geocoder/locations/address';

    public function geocode(User $user): void
    {
        $address = $user->addressOnFile();

        $user->forceFill(['address_latitude' => null, 'address_longitude' => null, 'address_geocoded_at' => null]);

        if (config('services.address_geocoder.driver', 'census') !== 'census'
            || ! filled($address->line1)
            || (Regions::country($address->country) ?? 'US') !== 'US'
            || ! (filled($address->postalCode) || (filled($address->city) && filled($address->state)))) {
            return;
        }

        try {
            $response = Http::timeout(6)->acceptJson()->get(self::ENDPOINT, array_filter([
                'street'    => $address->line1,
                'city'      => $address->city,
                'state'     => $address->state,
                'zip'       => $address->postalCode,
                'benchmark' => 'Public_AR_Current',
                'format'    => 'json',
            ]));

            $match = $response->successful() ? ($response->json('result.addressMatches.0.coordinates') ?? null) : null;

            if (is_array($match) && isset($match['x'], $match['y'])) {
                $user->forceFill([
                    'address_latitude'    => round((float) $match['y'], 6),
                    'address_longitude'   => round((float) $match['x'], 6),
                    'address_geocoded_at' => now(),
                ]);
            }
        } catch (Throwable $e) {
            Log::info('Address geocoding skipped: '.$e->getMessage(), ['user_id' => $user->id]);
        }
    }
}
