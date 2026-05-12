<?php

namespace Database\Seeders;

use App\Models\DeveloperExchangeMapping;
use App\Models\ExchangeNetwork;
use App\Models\PropertyDeveloper;
use Illuminate\Database\Seeder;

/**
 * Seeds the lookup tables for Vacation Club Exchange Detection (FR-9.x).
 *
 * Safe to re-run: every row is keyed via firstOrCreate on its slug so
 * existing rows are reused, not duplicated. Admins are expected to extend
 * via the admin UI; this seeder establishes the starting baseline derived
 * from the developer/exchange relationships in the original feature spec.
 *
 * Wired into DatabaseSeeder so a full `migrate --seed` populates it; can
 * also be called directly: `php artisan db:seed --class=ExchangeNetworksSeeder`.
 */
class ExchangeNetworksSeeder extends Seeder
{
    public function run(): void
    {
        // ── Exchange / banking networks ─────────────────────────────────
        $exchanges = [
            ['interval-international', 'Interval International', 'exchange', 'https://www.intervalworld.com'],
            ['rci',                    'RCI',                    'exchange', 'https://www.rci.com'],
            ['hgv-max',                'HGV Max',                'internal', 'https://www.hiltongrandvacations.com/en/hgv-max'],
            ['club-wyndham',           'Club Wyndham',           'internal', 'https://www.clubwyndham.com'],
            ['dvc-member-exchange',    'DVC Member Exchange',    'internal', 'https://disneyvacationclub.disney.go.com'],
            ['marriott-bonvoy',        'Marriott Bonvoy (points conversion)', 'banking', 'https://www.marriott.com/loyalty.mi'],
        ];
        $exchangeIdBySlug = [];
        foreach ($exchanges as [$slug, $name, $type, $url]) {
            $row = ExchangeNetwork::firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'type' => $type, 'website_url' => $url],
            );
            $exchangeIdBySlug[$slug] = $row->id;
        }

        // ── Developer brands ────────────────────────────────────────────
        $developers = [
            ['marriott-vacation-club',       'Marriott Vacation Club',       'Marriott'],
            ['hilton-grand-vacations',       'Hilton Grand Vacations',       'Hilton'],
            ['wyndham-destinations',         'Wyndham Destinations',         'Wyndham'],
            ['disney-vacation-club',         'Disney Vacation Club',         'Disney'],
            ['bluegreen-vacations',          'Bluegreen Vacations',          'Bluegreen'],
            ['westgate-resorts',             'Westgate Resorts',             'Westgate'],
            ['holiday-inn-club-vacations',   'Holiday Inn Club Vacations',   'IHG'],
            ['diamond-resorts',              'Diamond Resorts',              'Hilton'],   // Diamond was acquired by HGV
            ['vistana-signature-experiences','Vistana Signature Experiences','Marriott'], // Vistana → MVC umbrella
        ];
        $developerIdBySlug = [];
        foreach ($developers as [$slug, $name, $brand]) {
            $row = PropertyDeveloper::firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'parent_brand' => $brand],
            );
            $developerIdBySlug[$slug] = $row->id;
        }

        // ── Developer → Exchange mappings ───────────────────────────────
        // [developer_slug, exchange_slug, confidence, priority, conditions]
        $mappings = [
            ['marriott-vacation-club',       'interval-international', 100, 10, null],
            ['marriott-vacation-club',       'marriott-bonvoy',         80,  5, null],
            ['vistana-signature-experiences','interval-international',  95,  9, null],

            ['hilton-grand-vacations',       'hgv-max',                 90, 10, null],
            ['hilton-grand-vacations',       'rci',                     85,  7, null],
            ['diamond-resorts',              'hgv-max',                 75,  7, null],
            ['diamond-resorts',              'rci',                     80,  8, null],

            ['wyndham-destinations',         'rci',                     95, 10, null],
            ['wyndham-destinations',         'club-wyndham',            85,  6, null],

            ['disney-vacation-club',         'dvc-member-exchange',    100, 10, null],
            ['disney-vacation-club',         'interval-international',  70,  5, null], // II as a 2nd-tier option

            ['bluegreen-vacations',          'rci',                     95, 10, null],

            // Westgate is the canonical "depends on resort" case — both II and
            // RCI are active across its portfolio. Location condition nudges
            // confidence based on the property text mentioning known cities.
            ['westgate-resorts',             'interval-international',  75,  7, [
                'locations' => ['Orlando', 'Las Vegas', 'Kissimmee'],
            ]],
            ['westgate-resorts',             'rci',                     75,  7, [
                'locations' => ['Branson', 'Park City', 'Smoky Mountain'],
            ]],

            ['holiday-inn-club-vacations',   'rci',                     95, 10, null],
        ];

        foreach ($mappings as [$devSlug, $excSlug, $confidence, $priority, $conditions]) {
            if (! isset($developerIdBySlug[$devSlug], $exchangeIdBySlug[$excSlug])) {
                continue;
            }
            DeveloperExchangeMapping::updateOrCreate(
                [
                    'developer_id'        => $developerIdBySlug[$devSlug],
                    'exchange_network_id' => $exchangeIdBySlug[$excSlug],
                ],
                [
                    'confidence' => $confidence,
                    'priority'   => $priority,
                    'conditions' => $conditions,
                ],
            );
        }

        $devCount = count($developerIdBySlug);
        $exCount  = count($exchangeIdBySlug);
        $mapCount = count($mappings);

        if ($this->command) {
            $this->command->info("ExchangeNetworksSeeder: {$exCount} networks · {$devCount} developers · {$mapCount} mappings");
        }

        // Backfill: any enquiries/properties that predate this seeder (or
        // were inserted via DB::table — which bypasses the observer) won't
        // have an exchange_detection snapshot. Detect once now so they show
        // up correctly in the admin queue and the member dashboard.
        $detector = app(\App\Services\Exchange\ExchangeDetectionService::class);

        $enquiryBackfilled = 0;
        \App\Models\MemberEnquiry::query()
            ->whereNull('exchange_detection')
            ->each(function ($e) use ($detector, &$enquiryBackfilled) {
                $det = $detector->detect($e->club, $e->property);
                $e->forceFill(['exchange_detection' => $det])->saveQuietly();
                $enquiryBackfilled++;
            });

        $propertyBackfilled = 0;
        \App\Models\Property::query()
            ->where('listing_source', 'managed')
            ->whereNull('exchange_detection')
            ->each(function ($p) use ($detector, &$propertyBackfilled) {
                $det = $detector->detect($p->title, $p->city);
                $p->forceFill(['exchange_detection' => $det])->saveQuietly();
                $propertyBackfilled++;
            });

        if ($this->command && ($enquiryBackfilled + $propertyBackfilled > 0)) {
            $this->command->info("ExchangeNetworksSeeder backfill: {$enquiryBackfilled} enquiries · {$propertyBackfilled} managed listings");
        }
    }
}
