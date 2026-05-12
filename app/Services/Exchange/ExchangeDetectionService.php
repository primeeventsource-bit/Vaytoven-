<?php

namespace App\Services\Exchange;

use App\Models\DeveloperExchangeMapping;
use App\Models\PropertyDeveloper;
use Illuminate\Support\Carbon;

/**
 * Vacation Club Exchange Detection (FR-9.x).
 *
 * Given a club/brand string (and optional property/resort string), looks
 * up the registered exchange/banking networks the property likely belongs
 * to and returns a snapshot suitable for storage on members_enquiries or
 * properties.exchange_detection.
 *
 * Decision logic:
 *   - Normalize the club string (lowercase, collapse whitespace).
 *   - Match against property_developers — slug exact, then name exact,
 *     then a fuzzy "either contains the other" pass.
 *   - For each matching developer, pull its exchange mappings and adjust
 *     each mapping's baseline confidence by any condition bumps (location
 *     match in the property string adds +10; mismatch subtracts -20).
 *   - Resolve a status:
 *       no_match     — no developer matched
 *       auto         — exactly one match with confidence >= 80
 *       needs_review — multiple matches OR a single low-confidence match
 *
 * Lookup-only; never throws on bad input — returns a no_match snapshot so
 * the calling observer can save the row without dropping the create.
 */
class ExchangeDetectionService
{
    public const CONFIDENCE_AUTO_THRESHOLD = 80;
    public const CONDITION_LOCATION_BUMP = 10;
    public const CONDITION_LOCATION_PENALTY = 20;

    /**
     * @return array{
     *   status: string,
     *   matched_developer_slug: ?string,
     *   matched_developer_name: ?string,
     *   matches: array<int, array{exchange_slug: string, exchange_name: string, confidence: int, conditions: ?array}>,
     *   detected_at: string,
     *   input_club: ?string,
     *   input_property: ?string,
     *   confirmed_by_user_id: ?int,
     *   confirmed_at: ?string,
     *   confirmed_exchange_slug: ?string,
     * }
     */
    public function detect(?string $clubOrBrand, ?string $propertyText = null): array
    {
        $base = [
            'status'                  => 'no_match',
            'matched_developer_slug'  => null,
            'matched_developer_name'  => null,
            'matches'                 => [],
            'detected_at'             => Carbon::now()->toIso8601String(),
            'input_club'              => $clubOrBrand,
            'input_property'          => $propertyText,
            'confirmed_by_user_id'    => null,
            'confirmed_at'            => null,
            'confirmed_exchange_slug' => null,
        ];

        $normalized = $this->normalize($clubOrBrand ?? '');
        if ($normalized === '') {
            return $base;
        }

        $developer = $this->findDeveloperByName($normalized);
        if (! $developer) {
            return $base;
        }

        $matches = $this->mappingsForDeveloper($developer, $propertyText);
        $status = $this->resolveStatus($matches);

        return array_merge($base, [
            'status'                 => $status,
            'matched_developer_slug' => $developer->slug,
            'matched_developer_name' => $developer->name,
            'matches'                => $matches,
        ]);
    }

    /**
     * Records an explicit admin/specialist confirmation onto an existing
     * detection snapshot. Returns the updated array — caller is responsible
     * for saving it back to the row.
     */
    public function confirm(array $detection, int $userId, string $exchangeSlug): array
    {
        $detection['status']                  = 'confirmed';
        $detection['confirmed_by_user_id']    = $userId;
        $detection['confirmed_at']            = Carbon::now()->toIso8601String();
        $detection['confirmed_exchange_slug'] = $exchangeSlug;
        return $detection;
    }

    /** Normalize a free-text club name for matching. */
    public function normalize(string $name): string
    {
        $name = strtolower(trim($name));
        // collapse runs of whitespace to a single space
        return preg_replace('/\s+/u', ' ', $name) ?? '';
    }

    private function findDeveloperByName(string $normalized): ?PropertyDeveloper
    {
        // Slug exact (most reliable — the seeder uses kebab-case slugs).
        $bySlug = PropertyDeveloper::query()
            ->where('slug', str_replace(' ', '-', $normalized))
            ->first();
        if ($bySlug) {
            return $bySlug;
        }

        // Name exact (case-insensitive).
        $byName = PropertyDeveloper::query()
            ->whereRaw('LOWER(name) = ?', [$normalized])
            ->first();
        if ($byName) {
            return $byName;
        }

        // Fuzzy: either side contains the other. Limits to the first
        // hit since multiple substring matches indicates ambiguous input
        // that an admin should resolve.
        return PropertyDeveloper::query()
            ->whereRaw('LOWER(name) LIKE ?', ['%'.$normalized.'%'])
            ->orWhereRaw('? LIKE CONCAT(\'%\', LOWER(name), \'%\')', [$normalized])
            ->first();
    }

    /**
     * @return array<int, array{exchange_slug: string, exchange_name: string, confidence: int, conditions: ?array}>
     */
    private function mappingsForDeveloper(PropertyDeveloper $developer, ?string $propertyText): array
    {
        $haystack = $propertyText ? strtolower($propertyText) : '';

        $mappings = $developer->exchangeMappings()
            ->with('exchange')
            ->orderByDesc('priority')
            ->orderByDesc('confidence')
            ->get();

        return $mappings
            ->map(function (DeveloperExchangeMapping $m) use ($haystack) {
                $confidence = (int) $m->confidence;
                $conditions = $m->conditions ?? [];

                if (! empty($conditions['locations']) && $haystack !== '') {
                    $locationMatched = false;
                    foreach ($conditions['locations'] as $loc) {
                        if (str_contains($haystack, strtolower((string) $loc))) {
                            $locationMatched = true;
                            break;
                        }
                    }
                    $confidence += $locationMatched
                        ? self::CONDITION_LOCATION_BUMP
                        : -self::CONDITION_LOCATION_PENALTY;
                }

                $confidence = max(0, min(100, $confidence));

                return [
                    'exchange_slug' => $m->exchange->slug,
                    'exchange_name' => $m->exchange->name,
                    'confidence'    => $confidence,
                    'conditions'    => $conditions ?: null,
                ];
            })
            ->sortByDesc('confidence')
            ->values()
            ->all();
    }

    private function resolveStatus(array $matches): string
    {
        if (empty($matches)) {
            return 'no_match';
        }
        if (count($matches) === 1 && $matches[0]['confidence'] >= self::CONFIDENCE_AUTO_THRESHOLD) {
            return 'auto';
        }
        // Multiple matches OR a low-confidence single match — needs human.
        return 'needs_review';
    }
}
