<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * The featured convention centers, and how they connect to listings.
 *
 * One place that reads the config, so the page, the search filter and the
 * sitemap cannot drift apart. The filter in particular has to agree with the
 * page about what "McCormick Place Area" means, or someone clicks through from
 * a convention and lands on results for somewhere else.
 */
class EventCenters
{
    /** @return Collection<int, array<string, mixed>> */
    public static function all(): Collection
    {
        return collect(config('event-centers', []))
            ->map(fn (array $center) => $center + [
                'label' => $center['name'].' Area',
            ])
            ->values();
    }

    /** @return array<string, mixed>|null */
    public static function find(?string $slug): ?array
    {
        if (! $slug) {
            return null;
        }

        return static::all()->firstWhere('slug', $slug);
    }

    /**
     * The search parameters a center's "Explore properties nearby" button uses.
     *
     * Returned as query parameters rather than applied here, so the visitor
     * lands on a normal, shareable, bookmarkable search URL with the filter
     * visibly set — not on a special page that happens to be pre-filtered and
     * loses its filter the moment they change anything else.
     *
     * @return array<string, string>
     */
    public static function searchQuery(string $slug): array
    {
        $center = static::find($slug);

        return $center ? ['event_center' => $center['slug']] : [];
    }

    /**
     * Options for the search filter dropdown.
     *
     * @return array<string, string> slug => label
     */
    public static function filterOptions(): array
    {
        return static::all()->pluck('label', 'slug')->all();
    }
}
