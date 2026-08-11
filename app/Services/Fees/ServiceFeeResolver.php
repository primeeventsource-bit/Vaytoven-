<?php

namespace App\Services\Fees;

use App\Enums\FeeStructure;
use App\Models\Property;
use App\Models\ServiceFeeConfig;
use App\Services\Bookings\QuoteCalculator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;

/**
 * Decides which Vaytoven service fee configuration applies to a listing.
 *
 * Resolution is most-specific-first:
 *
 *   1. a config scoped to this property
 *   2. a config scoped to this property's host
 *   3. a config scoped to this listing source (host / managed)
 *   4. the platform default config
 *   5. a hard-coded fallback, only if the table is empty or unmigrated
 *
 * On top of that, a `fee_structure` set directly on the property or the host
 * record overrides the STRUCTURE the resolved config specifies, while keeping
 * that config's rates. That is what lets an operator flip one host to
 * Single-Fee without authoring a whole config row for them.
 *
 * Fails open to the documented defaults rather than throwing: a misconfigured
 * fee table must never take checkout down.
 */
class ServiceFeeResolver
{
    private const CACHE_KEY = 'service_fee_configs:in_effect';

    private const CACHE_TTL = 900;

    /** Used only when no configuration exists at all. */
    public const FALLBACK = [
        'fee_structure' => 'split',
        'split_host_bps' => 300,     // 3%
        'split_guest_bps' => 1500,   // 15%
        'single_host_bps' => 1550,   // 15.5%
    ];

    public function resolve(?Property $property): ResolvedServiceFee
    {
        $config = $this->bestConfigFor($property);

        // A structure set on the property, else on the host, overrides the
        // resolved config's own structure.
        $structure = $this->structureOverrideFor($property) ?? $config?->fee_structure ?? FeeStructure::Split;

        if ($config === null) {
            return $this->legacyFallback($structure);
        }

        return new ResolvedServiceFee(
            structure: $structure,
            hostBps: $structure === FeeStructure::Single
                ? $config->single_host_bps
                : $config->split_host_bps,
            guestBps: $structure === FeeStructure::Single ? 0 : $config->split_guest_bps,
            configId: $config->id,
        );
    }

    /**
     * With no configuration in effect, defer to the pre-existing fee settings
     * rather than to a constant.
     *
     * This matters for more than tests: `fees.guest_service_pct` and the
     * active fee schedule are editable in the Settings console today. If this
     * returned a hard-coded number, an operator changing them would silently
     * see no effect — the worst kind of failure, because the UI confirms the
     * save. A ServiceFeeConfig supersedes them; until one exists they still
     * drive the quote exactly as before.
     */
    private function legacyFallback(FeeStructure $structure): ResolvedServiceFee
    {
        // Whole percent in the old settings; basis points here.
        $guestBps = QuoteCalculator::guestServicePct() * 100;
        $splitHostBps = ((int) setting('fees.host_commission_pct', 3)) * 100;

        return new ResolvedServiceFee(
            structure: $structure,
            hostBps: $structure === FeeStructure::Single
                ? self::FALLBACK['single_host_bps']
                : $splitHostBps,
            guestBps: $structure === FeeStructure::Single ? 0 : $guestBps,
            configId: null,
        );
    }

    private function structureOverrideFor(?Property $property): ?FeeStructure
    {
        if ($property === null) {
            return null;
        }

        $raw = $property->fee_structure ?: $property->host?->fee_structure;

        return $raw ? FeeStructure::tryFrom((string) $raw) : null;
    }

    /**
     * The in-effect configs are read on every quote, so they are cached as a
     * whole small collection and filtered in PHP — one query per 15 minutes
     * rather than four scoped queries per page view.
     */
    private function bestConfigFor(?Property $property): ?ServiceFeeConfig
    {
        $candidates = $this->inEffectConfigs();

        if ($candidates->isEmpty()) {
            return null;
        }

        $matches = $candidates->filter(function (ServiceFeeConfig $config) use ($property) {
            return match ($config->scope_type) {
                ServiceFeeConfig::SCOPE_DEFAULT => true,
                ServiceFeeConfig::SCOPE_PROPERTY => $property
                    && (string) $config->scope_value === (string) $property->id,
                ServiceFeeConfig::SCOPE_HOST => $property
                    && (string) $config->scope_value === (string) $property->host_id,
                ServiceFeeConfig::SCOPE_LISTING_SOURCE => $property
                    && (string) $config->scope_value === (string) $property->listing_source,
                default => false,
            };
        });

        // Most specific wins; newest effective_from breaks a tie.
        return $matches
            ->sortByDesc(fn (ServiceFeeConfig $c) => [$c->precedence(), $c->effective_from?->timestamp ?? 0, $c->id])
            ->first();
    }

    /** @return \Illuminate\Support\Collection<int, ServiceFeeConfig> */
    private function inEffectConfigs()
    {
        try {
            return Cache::remember(
                self::CACHE_KEY,
                self::CACHE_TTL,
                fn () => ServiceFeeConfig::query()->inEffect()->get(),
            );
        } catch (QueryException) {
            // Table not migrated yet — fall back rather than break checkout.
            return collect();
        }
    }

    public static function bustCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
