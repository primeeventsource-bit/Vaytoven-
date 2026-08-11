<?php

namespace App\Services\Bookings;

use App\Models\FeeSchedule;
use App\Models\Property;
use App\Models\TaxRule;
use App\Services\Fees\ServiceFeeResolver;
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
     * @param  Property|null  $property  Resolves the host/guest fee structure.
     *                                   Null keeps the pre-structure behaviour,
     *                                   which is what the unit tests of the old
     *                                   math rely on.
     * @return array{
     *     subtotal_cents:int, service_fee_cents:int, tax_cents:int, total_cents:int,
     *     fee_structure:string, host_fee_bps:int, guest_fee_bps:int,
     *     host_fee_cents:int, host_net_cents:int, service_fee_config_id:int|null
     * }
     */
    public static function breakdown(int $rateCents, int $nights, int $cleaningCents, ?Property $property = null): array
    {
        $subtotal = $rateCents * $nights;

        $fee = app(ServiceFeeResolver::class)->resolve($property);

        // Guest service fee is charged on the nightly subtotal only — cleaning
        // is a host cost passed through, not something Vaytoven marks up. Under
        // Single-Fee the guest rate is 0, so this is 0 and their total equals
        // the stay price, which is the whole point of that structure.
        $serviceFee = $fee->guestFeeCents($subtotal);

        $tax = (int) round(($subtotal + $cleaningCents + $serviceFee) * self::taxBps() / 10000);

        // The host's gross is what the guest pays for the stay itself: nights
        // plus cleaning. Their fee comes out of that, never out of tax (which
        // is not Vaytoven's money) nor out of the guest service fee.
        $hostGross = $subtotal + $cleaningCents;

        return [
            'subtotal_cents' => $subtotal,
            'service_fee_cents' => $serviceFee,
            'tax_cents' => $tax,
            'total_cents' => $subtotal + $cleaningCents + $serviceFee + $tax,

            // Snapshot fields — persisted onto the booking so a later rate
            // change can never rewrite what this transaction actually was.
            'fee_structure' => $fee->structure->value,
            'host_fee_bps' => $fee->hostBps,
            'guest_fee_bps' => $fee->guestBps,
            'host_fee_cents' => $fee->hostFeeCents($hostGross),
            'host_net_cents' => $fee->hostNetCents($hostGross),
            'service_fee_config_id' => $fee->configId,
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
