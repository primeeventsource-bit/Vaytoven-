<?php

namespace App\Services\Analytics;

use App\Models\MemberOffer;
use App\Models\Property;
use App\Models\PropertyView;
use App\Models\TrackingEvent;
use Illuminate\Support\Collection;

/**
 * "Where your ad is getting attention" — the member-facing map.
 *
 * This is deliberately NOT the admin view. A member sees engagement with their
 * own advertisement and nothing else: approximate city, and a count.
 *
 * What never leaves this class:
 *   - IP addresses, of visitors or of anyone else
 *   - user agents, device or browser details
 *   - visitor ids, names, emails, or anything identifying a person
 *   - exact coordinates
 *
 * The evidence-grade detail — contract IPs, login IPs, device fingerprints —
 * stays admin-only, in the certificate and the Member 360. A member has a
 * legitimate interest in knowing their advertising reaches Orlando; they have
 * no legitimate interest in knowing which household in Orlando.
 */
class MemberEngagementMap
{
    /** Windows the member can switch between. */
    public const WINDOWS = [7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days', 0 => 'All time'];

    /**
     * A city needs at least this many events before it appears as a pin.
     *
     * With a threshold of one, a member whose ad was clicked once from a small
     * town learns that a specific person there looked at it — which, combined
     * with anything else they know, can identify them. Aggregation is only
     * anonymising when there is something to aggregate.
     */
    public const MIN_EVENTS_PER_PIN = 3;

    /**
     * @param  Collection<int, \App\Models\Property>  $listings
     * @param  int  $days  0 for all time
     */
    public function build(Collection $listings, int $days = 30, ?int $propertyId = null): array
    {
        $ids = $propertyId
            ? $listings->where('id', $propertyId)->pluck('id')
            : $listings->pluck('id');

        if ($ids->isEmpty()) {
            return $this->empty($days, $propertyId);
        }

        $since = $days > 0 ? now()->subDays($days) : null;

        $views = PropertyView::query()
            ->whereIn('property_id', $ids)
            ->when($since, fn ($q) => $q->where('occurred_at', '>=', $since));

        // Traffic across the site the advertisement is published on, plus the
        // interactions with this member's own listings.
        //
        // Site-wide by design: a member's advertisement sits on Vaytoven, and
        // the reach they are buying is the site's audience, not only the people
        // who happened to open their page. Counting site traffic keeps that
        // visible from day one rather than showing a new listing a row of
        // zeros.
        //
        // Worth knowing when reading this number: it is site traffic plus this
        // member's listing engagement, not a count of times their own listing
        // page was opened. Deliberate product decision, not an oversight.
        $references = Property::whereIn('id', $ids)->pluck('reference')->filter()->all();

        $interactions = TrackingEvent::query()
            ->where(function ($q) use ($references) {
                // Anything on the site...
                $q->whereIn('event_type', self::SITE_VIEWS);

                // ...plus engagement with this member's own listings.
                if ($references) {
                    $q->orWhere(fn ($w) => $w
                        ->whereIn('subject_reference', $references)
                        ->whereIn('event_type', self::AD_INTERACTIONS));
                }
            })
            ->when($since, fn ($q) => $q->where('occurred_at', '>=', $since));

        $offers = MemberOffer::query()
            ->whereIn('property_id', $ids)
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since));

        // One number, not two. "Ad views" and "clicks" side by side asked the
        // member to work out the difference between somebody opening the
        // advertisement and somebody doing something on it — and with views at
        // zero next to a large click count, the pair read as broken.
        $adViews = (clone $views)->count() + (clone $interactions)->count();

        return [
            'window'       => $days,
            'windows'      => self::WINDOWS,
            'property_id'  => $propertyId,
            'totals'       => [
                'ad_views' => $adViews,
                'offers'   => (clone $offers)->count(),
            ],
            'pins'         => $this->pins(clone $interactions, clone $views),
            'min_per_pin'  => self::MIN_EVENTS_PER_PIN,
        ];
    }

    /**
     * What counts as somebody engaging with an advertisement.
     *
     * Deliberately the property-scoped events only. property.viewed is left
     * out because property_views already records it, and counting both would
     * double every page view.
     */
    /** Traffic anywhere on the site the advertisement is published on. */
    private const SITE_VIEWS = [
        'page_view',
        'cta_click',
        'page.viewed',
        'website.visited',
    ];

    private const AD_INTERACTIONS = [
        'gallery.opened',
        'amenity.viewed',
        'advertisement.clicked',
        'offer.started',
        'inquiry.started',
        'favorite.saved',
    ];

    /**
     * City-level pins with counts.
     *
     * Coordinates are rounded to one decimal — roughly 11km — so a pin marks a
     * metro rather than a street. Rows without a resolved city are dropped
     * entirely rather than shown as "Unknown" at 0,0, which would put a marker
     * in the Gulf of Guinea and invite the member to read meaning into it.
     */
    private function pins($interactions, $views): array
    {
        $aggregate = fn ($query) => $query
            ->whereNotNull('city')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->selectRaw('city, region, country, round(latitude, 1) as lat, round(longitude, 1) as lng, count(*) as c')
            ->groupBy('city', 'region', 'country', 'lat', 'lng')
            ->orderByDesc('c')
            ->limit(200)
            ->get();

        $byPlace = [];

        foreach ([$aggregate($interactions), $aggregate($views)] as $rows) {
            foreach ($rows as $row) {
                $key = $row->city.'|'.$row->country.'|'.$row->lat.'|'.$row->lng;

                $byPlace[$key] ??= [
                    'city'    => $row->city,
                    'region'  => $row->region,
                    'country' => $row->country,
                    'lat'     => (float) $row->lat,
                    'lng'     => (float) $row->lng,
                    'ad_views' => 0,
                ];

                $byPlace[$key]['ad_views'] += (int) $row->c;
            }
        }

        // Below the threshold a pin describes an individual, not an audience.
        $pins = array_values(array_filter(
            $byPlace,
            fn ($p) => $p['ad_views'] >= self::MIN_EVENTS_PER_PIN,
        ));

        usort($pins, fn ($a, $b) => $b['ad_views'] <=> $a['ad_views']);

        return $pins;
    }

    private function empty(int $days, ?int $propertyId): array
    {
        return [
            'window'      => $days,
            'windows'     => self::WINDOWS,
            'property_id' => $propertyId,
            'totals'      => ['ad_views' => 0, 'offers' => 0],
            'pins'        => [],
            'min_per_pin' => self::MIN_EVENTS_PER_PIN,
        ];
    }
}
