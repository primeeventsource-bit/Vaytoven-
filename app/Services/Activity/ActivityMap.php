<?php

namespace App\Services\Activity;

use App\Enums\ActivityType;
use App\Models\TrackingEvent;
use Illuminate\Support\Collection;

/**
 * Activity aggregated into map pins.
 *
 * One pin per approximate GeoIP location, carrying the counts staff actually
 * ask about: visits, property views, ad clicks, registrations, offers,
 * contracts signed, activations.
 *
 * Aggregation happens in SQL. Pulling every event into PHP to group it works
 * on a demo database and stops working on the one that matters — and this is
 * the screen most likely to be pointed at a year of data.
 *
 * Unlike the member-facing engagement map, this applies no minimum-events
 * threshold. That threshold exists so a member cannot infer an individual from
 * their own analytics; staff investigating a dispute are entitled to see a
 * single event, and hiding it would make the tool useless for the job it is
 * for. What does NOT change is the honesty of the label: a GeoIP location is
 * approximate, and is never presented as a physical address.
 */
class ActivityMap
{
    /**
     * Marker layers, in the order they appear in the legend.
     *
     * @return array<string, array{label: string, types: array<int, string>}>
     */
    public static function layers(): array
    {
        return [
            'visitors' => [
                'label' => 'Website visitors',
                'types' => [ActivityType::WebsiteVisited->value, ActivityType::PageViewed->value],
            ],
            'property_views' => [
                'label' => 'Property views',
                'types' => [ActivityType::PropertyViewed->value],
            ],
            'ad_clicks' => [
                'label' => 'Ad clicks',
                'types' => [ActivityType::AdvertisementClicked->value],
            ],
            'logins' => [
                'label' => 'Account logins',
                'types' => [ActivityType::LoginSucceeded->value],
            ],
            'registrations' => [
                'label' => 'Accounts created',
                'types' => [ActivityType::AccountCreated->value],
            ],
            'offers' => [
                'label' => 'Offers submitted',
                'types' => [ActivityType::OfferSubmitted->value],
            ],
            'contracts' => [
                'label' => 'Contracts signed',
                'types' => [ActivityType::ContractSigned->value],
            ],
            'activations' => [
                'label' => 'Property activations',
                'types' => [ActivityType::AdvertisementActivated->value],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function pins(array $filters = []): Collection
    {
        $layer = $filters['layer'] ?? 'all';
        $types = $layer === 'all' ? null : (self::layers()[$layer]['types'] ?? ['__none__']);

        $query = TrackingEvent::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if ($types !== null) {
            $query->whereIn('event_type', $types);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('occurred_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('occurred_at', '<=', $filters['to']);
        }

        // Grouped by city rather than by coordinate. Two lookups of the same
        // city can differ in the sixth decimal place, which would scatter one
        // place across several pins sitting on top of each other.
        return $query
            ->selectRaw('city, region, country, event_type,
                         AVG(latitude) as lat, AVG(longitude) as lng,
                         COUNT(*) as events')
            ->groupBy('city', 'region', 'country', 'event_type')
            ->get()
            ->groupBy(fn ($row) => implode('|', [$row->city, $row->region, $row->country]))
            ->map(function (Collection $rows, string $key) {
                [$city, $region, $country] = array_pad(explode('|', $key), 3, null);

                $breakdown = [];
                $total = 0;

                foreach ($rows as $row) {
                    $label = ActivityType::tryFrom($row->event_type)?->label() ?? $row->event_type;
                    $breakdown[$label] = ($breakdown[$label] ?? 0) + (int) $row->events;
                    $total += (int) $row->events;
                }

                arsort($breakdown);

                return [
                    'label'     => trim(collect([$city, $region, $country])->filter()->implode(', ')) ?: 'Unresolved',
                    'lat'       => round((float) $rows->avg('lat'), 4),
                    'lng'       => round((float) $rows->avg('lng'), 4),
                    'total'     => $total,
                    'breakdown' => $breakdown,
                ];
            })
            ->sortByDesc('total')
            ->values();
    }
}
