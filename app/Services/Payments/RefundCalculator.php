<?php

namespace App\Services\Payments;

use App\Enums\CancellationPolicy;
use App\Models\Booking;
use Carbon\CarbonImmutable;

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

        [$subtotalPct, $tier] = match ($booking->cancellation_policy) {
            CancellationPolicy::Flexible      => self::flexibleTier($hoursUntilCheckIn),
            CancellationPolicy::Moderate      => self::moderateTier($hoursUntilCheckIn),
            CancellationPolicy::Strict        => self::strictTier($hoursUntilCheckIn),
            CancellationPolicy::NonRefundable => [0, 'none'],
            null                              => [0, 'none'],
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

    /** @return array{0: float, 1: string} */
    private static function flexibleTier(int $hoursUntilCheckIn): array
    {
        return match (true) {
            $hoursUntilCheckIn >= 24 => [1.0, 'full'],
            default                  => [0.0, 'none'],
        };
    }

    /** @return array{0: float, 1: string} */
    private static function moderateTier(int $hoursUntilCheckIn): array
    {
        return match (true) {
            $hoursUntilCheckIn >= 24 * 5 => [1.0, 'full'],     // >= 5 days
            $hoursUntilCheckIn >= 24     => [0.5, 'partial'],  // 24h..5d
            default                      => [0.0, 'none'],
        };
    }

    /** @return array{0: float, 1: string} */
    private static function strictTier(int $hoursUntilCheckIn): array
    {
        return match (true) {
            $hoursUntilCheckIn >= 24 * 7 => [0.5, 'partial'],
            default                      => [0.0, 'none'],
        };
    }
}
