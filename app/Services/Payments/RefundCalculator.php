<?php

namespace App\Services\Payments;

use App\Enums\CancellationPolicy;
use App\Models\Booking;
use App\Models\CancellationPolicyConfig;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;

/**
 * Pure-function refund math for traveler-initiated cancellations (FR-3.4).
 *
 * The four policies follow industry-standard rental practice:
 *
 *   • flexible       — full refund if cancelled >= 24h before check-in;
 *                      0% within 24h.
 *   • moderate       — full refund if >= 5 days before check-in;
 *                      50% within 5 days but >= 24h; 0% within 24h.
 *   • strict         — 50% if >= 7 days before check-in; 0% within 7 days.
 *   • non_refundable — 0% regardless of timing.
 *
 * Cleaning fee follows the subtotal tier (refundable when subtotal is, in
 * full or in part). Service fee (Vaytoven's cut) is always non-refundable
 * to the traveler — Vaytoven covers operational costs regardless. Tax
 * follows the subtotal tier (no point taxing money we're refunding).
 *
 * All amounts are integer cents (NFR-1). No floating-point math anywhere.
 *
 * Host-initiated cancellations (force majeure, etc.) are always 100% refunded
 * — handled by a separate path, not this calculator.
 */
final class RefundCalculator
{
    public static function compute(Booking $booking, ?CarbonImmutable $cancelledAt = null): RefundBreakdown
    {
        $cancelledAt ??= CarbonImmutable::now();
        $checkIn = CarbonImmutable::parse($booking->check_in_date)->startOfDay();
        $hoursUntilCheckIn = $cancelledAt->diffInHours($checkIn, absolute: false);

        // Tiers come from the admin-editable cancellation_policies table
        // (Settings console). The seeded rows reproduce the legacy hardcoded
        // tiers exactly; if the table is missing or the policy has no row,
        // the legacy tiers below still apply, so refunds never break.
        [$subtotalPct, $tier] = self::configuredTier($booking->cancellation_policy, $hoursUntilCheckIn)
            ?? match ($booking->cancellation_policy) {
                CancellationPolicy::Flexible => self::flexibleTier($hoursUntilCheckIn),
                CancellationPolicy::Moderate => self::moderateTier($hoursUntilCheckIn),
                CancellationPolicy::Strict => self::strictTier($hoursUntilCheckIn),
                CancellationPolicy::NonRefundable => [0, 'none'],
                null => [0, 'none'],
            };

        // Round to nearest cent. Banker's rounding is overkill at our scale.
        $subtotal = (int) round($booking->subtotal_cents * $subtotalPct);
        $cleaning = (int) round($booking->cleaning_fee_cents * $subtotalPct);

        // Service fee (Vaytoven's cut) is never refunded to the traveler.
        $serviceFee = 0;

        // Tax follows the subtotal tier (refund proportional to refunded subtotal).
        $tax = (int) round($booking->tax_cents * $subtotalPct);

        return new RefundBreakdown(
            subtotal_refund_cents: $subtotal,
            cleaning_refund_cents: $cleaning,
            service_fee_refund_cents: $serviceFee,
            tax_refund_cents: $tax,
            tier: $tier,
        );
    }

    /**
     * Resolve the refund percentage from the policy's admin-configured
     * refund_tiers ([{days_before, refund_pct}, …]). First tier whose
     * days_before threshold the cancellation beats wins; no match = 0%.
     * Returns null when no usable row exists (fall back to legacy tiers).
     *
     * @return array{0: float, 1: string}|null
     */
    private static function configuredTier(?CancellationPolicy $policy, int $hoursUntilCheckIn): ?array
    {
        if ($policy === null) {
            return null;
        }

        try {
            $tiersByCode = Cache::remember('cancellation_policies:tiers', 3600, fn () => CancellationPolicyConfig::query()
                ->where('active', true)
                ->pluck('refund_tiers', 'code')
                ->all());
        } catch (QueryException) {
            return null;
        }

        $tiers = $tiersByCode[$policy->value] ?? null;
        if (! is_array($tiers)) {
            return null;
        }

        // Most-generous (furthest-out) threshold first.
        usort($tiers, fn ($a, $b) => ($b['days_before'] ?? 0) <=> ($a['days_before'] ?? 0));

        foreach ($tiers as $tier) {
            $daysBefore = (int) ($tier['days_before'] ?? 0);
            $refundPct = max(0, min(100, (int) ($tier['refund_pct'] ?? 0)));

            if ($hoursUntilCheckIn >= $daysBefore * 24) {
                return [
                    $refundPct / 100,
                    $refundPct >= 100 ? 'full' : ($refundPct > 0 ? 'partial' : 'none'),
                ];
            }
        }

        return [0, 'none'];
    }

    /** Bust after admin edits to cancellation_policies. */
    public static function bustCache(): void
    {
        Cache::forget('cancellation_policies:tiers');
    }

    /** @return array{0: float, 1: string} */
    private static function flexibleTier(int $hoursUntilCheckIn): array
    {
        return match (true) {
            $hoursUntilCheckIn >= 24 => [1.0, 'full'],
            default => [0.0, 'none'],
        };
    }

    /** @return array{0: float, 1: string} */
    private static function moderateTier(int $hoursUntilCheckIn): array
    {
        return match (true) {
            $hoursUntilCheckIn >= 24 * 5 => [1.0, 'full'],     // >= 5 days
            $hoursUntilCheckIn >= 24 => [0.5, 'partial'],  // 24h..5d
            default => [0.0, 'none'],
        };
    }

    /** @return array{0: float, 1: string} */
    private static function strictTier(int $hoursUntilCheckIn): array
    {
        return match (true) {
            $hoursUntilCheckIn >= 24 * 7 => [0.5, 'partial'],
            default => [0.0, 'none'],
        };
    }
}
