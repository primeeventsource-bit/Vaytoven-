<?php

namespace App\Support\Location;

use Illuminate\Support\Str;

/**
 * Normalises the several ways one place gets written down.
 *
 * GeoIP providers say "Florida"; a member types "FL", "fla" or "florida";
 * countries arrive as "US", "USA" or "United States". Comparing the raw
 * strings would call a member in Miami, FL "a different area" from Miami,
 * Florida.
 */
final class Regions
{
    public const US_STATES = [
        'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California',
        'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware', 'DC' => 'District of Columbia',
        'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois',
        'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana',
        'ME' => 'Maine', 'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota',
        'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada',
        'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York',
        'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma', 'OR' => 'Oregon',
        'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina', 'SD' => 'South Dakota',
        'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont', 'VA' => 'Virginia',
        'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming',
        'PR' => 'Puerto Rico', 'VI' => 'U.S. Virgin Islands', 'GU' => 'Guam',
    ];

    private const COUNTRY_NAMES = [
        'US' => 'United States', 'CA' => 'Canada', 'MX' => 'Mexico', 'GB' => 'United Kingdom',
        'PA' => 'Panama', 'PR' => 'Puerto Rico', 'BS' => 'Bahamas', 'DO' => 'Dominican Republic',
        'JM' => 'Jamaica', 'CR' => 'Costa Rica', 'AU' => 'Australia', 'DE' => 'Germany', 'FR' => 'France',
        'ES' => 'Spain', 'IT' => 'Italy', 'NL' => 'Netherlands', 'IE' => 'Ireland', 'IN' => 'India',
        'PH' => 'Philippines', 'BR' => 'Brazil', 'CO' => 'Colombia',
    ];

    public static function country(?string $value): ?string
    {
        $v = strtoupper(trim((string) $value));

        if ($v === '') {
            return null;
        }

        return match ($v) {
            'USA', 'U.S.', 'U.S.A.', 'UNITED STATES', 'UNITED STATES OF AMERICA' => 'US',
            'UK', 'UNITED KINGDOM', 'GREAT BRITAIN'                               => 'GB',
            'CANADA'                                                              => 'CA',
            default => strlen($v) === 2 ? $v : (array_search(Str::title($v), self::COUNTRY_NAMES, true) ?: $v),
        };
    }

    public static function countryName(?string $code): ?string
    {
        $code = self::country($code);

        return $code ? (self::COUNTRY_NAMES[$code] ?? $code) : null;
    }

    /** A US state as its two-letter code; anything else lower-cased and trimmed. */
    public static function state(?string $value): ?string
    {
        $v = trim((string) $value);

        if ($v === '') {
            return null;
        }

        $upper = strtoupper($v);

        if (isset(self::US_STATES[$upper])) {
            return $upper;
        }

        foreach (self::US_STATES as $code => $name) {
            if (strcasecmp($name, $v) === 0) {
                return $code;
            }
        }

        return Str::lower($v);
    }

    public static function city(?string $value): ?string
    {
        $v = Str::lower(trim((string) $value));
        $v = preg_replace('/[^a-z0-9 ]+/', ' ', $v);
        $v = preg_replace('/\b(saint|ste?)\b/', 'st', (string) $v);
        $v = preg_replace('/\s+/', ' ', trim((string) $v));

        return $v !== '' ? $v : null;
    }

    /** Great-circle distance in miles. */
    public static function miles(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 3958.8;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
