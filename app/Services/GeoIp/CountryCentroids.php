<?php

namespace App\Services\GeoIp;

/**
 * ISO-3166 alpha-2 country code → geographic centroid (lat, lng).
 *
 * Used as a coarse pin location when an upstream geo provider gives us a
 * country code but no city-level coordinates (e.g., Cloudflare on the free
 * plan: cf-ipcountry only). The map ends up with one pin per visited
 * country instead of staying empty.
 *
 * Coordinates are approximate geographic centroids (not capital cities) so
 * visits from any part of a country land at a sensible "middle". Source:
 * public-domain country centroid datasets (Natural Earth, gov.uk, etc.).
 * The dataset is intentionally small — covers the ~70 countries likeliest
 * to show up in our analytics. Unknowns return null and just don't get a pin.
 */
final class CountryCentroids
{
    /** @var array<string, array{0: float, 1: float}> */
    private const COORDS = [
        'AE' => [23.4241, 53.8478],
        'AR' => [-38.4161, -63.6167],
        'AT' => [47.5162, 14.5501],
        'AU' => [-25.2744, 133.7751],
        'BE' => [50.5039, 4.4699],
        'BG' => [42.7339, 25.4858],
        'BR' => [-14.2350, -51.9253],
        'CA' => [56.1304, -106.3468],
        'CH' => [46.8182, 8.2275],
        'CL' => [-35.6751, -71.5430],
        'CN' => [35.8617, 104.1954],
        'CO' => [4.5709, -74.2973],
        'CZ' => [49.8175, 15.4730],
        'DE' => [51.1657, 10.4515],
        'DK' => [56.2639, 9.5018],
        'DO' => [18.7357, -70.1627],
        'EC' => [-1.8312, -78.1834],
        'EG' => [26.8206, 30.8025],
        'ES' => [40.4637, -3.7492],
        'FI' => [61.9241, 25.7482],
        'FR' => [46.2276, 2.2137],
        'GB' => [55.3781, -3.4360],
        'GR' => [39.0742, 21.8243],
        'HK' => [22.3193, 114.1694],
        'HR' => [45.1000, 15.2000],
        'HU' => [47.1625, 19.5033],
        'ID' => [-0.7893, 113.9213],
        'IE' => [53.4129, -8.2439],
        'IL' => [31.0461, 34.8516],
        'IN' => [20.5937, 78.9629],
        'IS' => [64.9631, -19.0208],
        'IT' => [41.8719, 12.5674],
        'JM' => [18.1096, -77.2975],
        'JO' => [30.5852, 36.2384],
        'JP' => [36.2048, 138.2529],
        'KE' => [-0.0236, 37.9062],
        'KR' => [35.9078, 127.7669],
        'LK' => [7.8731, 80.7718],
        'LU' => [49.8153, 6.1296],
        'MA' => [31.7917, -7.0926],
        'MX' => [23.6345, -102.5528],
        'MY' => [4.2105, 101.9758],
        'NG' => [9.0820, 8.6753],
        'NL' => [52.1326, 5.2913],
        'NO' => [60.4720, 8.4689],
        'NZ' => [-40.9006, 174.8860],
        'PA' => [8.5380, -80.7821],
        'PE' => [-9.1900, -75.0152],
        'PH' => [12.8797, 121.7740],
        'PL' => [51.9194, 19.1451],
        'PR' => [18.2208, -66.5901],
        'PT' => [39.3999, -8.2245],
        'RO' => [45.9432, 24.9668],
        'RS' => [44.0165, 21.0059],
        'RU' => [61.5240, 105.3188],
        'SA' => [23.8859, 45.0792],
        'SE' => [60.1282, 18.6435],
        'SG' => [1.3521, 103.8198],
        'SK' => [48.6690, 19.6990],
        'TH' => [15.8700, 100.9925],
        'TR' => [38.9637, 35.2433],
        'TW' => [23.6978, 120.9605],
        'UA' => [48.3794, 31.1656],
        'US' => [39.8283, -98.5795],
        'UY' => [-32.5228, -55.7658],
        'VE' => [6.4238, -66.5897],
        'VN' => [14.0583, 108.2772],
        'ZA' => [-30.5595, 22.9375],
    ];

    /**
     * @return array{0: float, 1: float}|null  [latitude, longitude] or null
     *         when the country isn't in the table (visit still records, just
     *         no map pin).
     */
    public static function for(?string $countryCode): ?array
    {
        if (! $countryCode) {
            return null;
        }
        return self::COORDS[strtoupper($countryCode)] ?? null;
    }
}
