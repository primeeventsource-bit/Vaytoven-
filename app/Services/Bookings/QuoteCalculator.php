<?php

namespace App\Services\Bookings;

use App\Models\FeeSchedule;
use App\Models\TaxRule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;

/**
 * Single source of truth for booking quote math. Previously the 12% service
 * fee and 8% tax were hardcoded (duplicated!) in BookingService and
 * BookingFlowController; both now delegate here, and the numbers come from
 * the admin console:
 *
 *   - service fee % : the active fee_schedule (fees.active_schedule_id) or,
 *                     when none is selected, setting('fees.guest_service_pct')
 *   - tax           : the first active booking_total tax_rule (rate_bps), or
 *                     the 800 bps legacy default
 *
 * All math is integer cents (NFR-1); percentages are whole ints and tax
 * rates basis points, so nothing here ever multiplies by a float rate.
 * Reads fall back to the pre-settings constants when the config tables
 * don't exist yet, so quotes keep working mid-deploy.
 */
final class QuoteCalculator
{
    public const DEFAULT_SERVICE_PCT = 12;

    public const DEFAULT_TAX_BPS = 800;

    /**
     * @return array{subtotal_cents:int, service_fee_cents:int, tax_cents:int, total_cents:int}
     */
    public static function breakdown(int $rateCents, int $nights, int $cleaningCents): array
    {
        $subtotal = $rateCents * $nights;
        $serviceFee = (int) round($subtotal * self::guestServicePct() / 100);
        $tax = (int) round(($subtotal + $cleaningCents + $serviceFee) * self::taxBps() / 10000);

        return [
            'subtotal_cents' => $subtotal,
            'service_fee_cents' => $serviceFee,
            'tax_cents' => $tax,
            'total_cents' => $subtotal + $cleaningCents + $serviceFee + $tax,
        ];
    }

    public static function guestServicePct(): int
    {
        $scheduleId = (int) setting('fees.active_schedule_id', 0);

        if ($scheduleId > 0) {
            try {
                $pct = Cache::remember(
                    "fee_schedules:{$scheduleId}:guest_pct",
                    3600,
                    fn () => FeeSchedule::query()->whereKey($scheduleId)->where('active', true)->value('guest_service_pct'),
                );

                if ($pct !== null) {
                    return (int) $pct;
                }
            } catch (QueryException) {
                // table missing — fall through to the scalar setting
            }
        }

        return (int) setting('fees.guest_service_pct', self::DEFAULT_SERVICE_PCT);
    }

    public static function taxBps(): int
    {
        try {
            $bps = Cache::remember('tax_rules:booking_total_bps', 3600, fn () => TaxRule::query()
                ->where('active', true)
                ->where('applies_to', 'booking_total')
                ->orderBy('id')
                ->value('rate_bps'));
        } catch (QueryException) {
            $bps = null;
        }

        return $bps === null ? self::DEFAULT_TAX_BPS : (int) $bps;
    }

    /** Called by the admin console after fee/tax collection writes. */
    public static function bustCache(): void
    {
        Cache::forget('tax_rules:booking_total_bps');
        // Fee schedule keys are per-id; the active id's key is the one that matters.
        $scheduleId = (int) setting('fees.active_schedule_id', 0);
        if ($scheduleId > 0) {
            Cache::forget("fee_schedules:{$scheduleId}:guest_pct");
        }
    }
}
