<?php

namespace App\Services\Analytics;

use App\Models\MemberOffer;
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

        $clicks = TrackingEvent::query()
            ->where('event_type', 'cta_click')
            ->when($since, fn ($q) => $q->where('occurred_at', '>=', $since));

        $offers = MemberOffer::query()
            ->whereIn('property_id', $ids)
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since));

        return [
            'window'       => $days,
            'windows'      => self::WINDOWS,
            'property_id'  => $propertyId,
            'totals'       => [
                'views'  => (clone $views)->count(),
                'clicks' => (clone $clicks)->count(),
                'offers' => (clone $offers)->count(),
            ],
            'pins'         => $this->pins(clone $clicks, clone $views),
            'min_per_pin'  => self::MIN_EVENTS_PER_PIN,
        ];
    }

    /**
     * City-level pins with counts.
     *
     * Coordinates are rounded to one decimal — roughly 11km — so a pin marks a
     * metro rather than a street. Rows without a resolved city are dropped
     * entirely rather than shown as "Unknown" at 0,0, which would put a marker
     * in the Gulf of Guinea and invite the member to read meaning into it.
     */
    private function pins($clicks, $views): array
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

        foreach ([['clicks', $aggregate($clicks)], ['views', $aggregate($views)]] as [$kind, $rows]) {
            foreach ($rows as $row) {
                $key = $row->city.'|'.$row->country.'|'.$row->lat.'|'.$row->lng;

                $byPlace[$key] ??= [
                    'city'    => $row->city,
                    'region'  => $row->region,
                    'country' => $row->country,
                    'lat'     => (float) $row->lat,
                    'lng'     => (float) $row->lng,
                    'clicks'  => 0,
                    'views'   => 0,
                ];

                $byPlace[$key][$kind] += (int) $row->c;
            }
        }

        // Below the threshold a pin describes an individual, not an audience.
        $pins = array_values(array_filter(
            $byPlace,
            fn ($p) => ($p['clicks'] + $p['views']) >= self::MIN_EVENTS_PER_PIN,
        ));

        usort($pins, fn ($a, $b) => ($b['clicks'] + $b['views']) <=> ($a['clicks'] + $a['views']));

        return $pins;
    }

    private function empty(int $days, ?int $propertyId): array
    {
        return [
            'window'      => $days,
            'windows'     => self::WINDOWS,
            'property_id' => $propertyId,
            'totals'      => ['views' => 0, 'clicks' => 0, 'offers' => 0],
            'pins'        => [],
            'min_per_pin' => self::MIN_EVENTS_PER_PIN,
        ];
    }
}
