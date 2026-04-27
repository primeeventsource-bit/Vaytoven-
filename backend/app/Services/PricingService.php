<?php

namespace App\Services;

use App\Models\Property;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use InvalidArgumentException;

/**
 * Owns all booking price calculations.
 *
 * Money is in integer cents throughout — never decimals or floats.
 * Output of `quote()` is meant to be snapshotted onto the Booking
 * row and never recalculated from current Property data afterwards.
 */
class PricingService
{
    /** Guest service fee in basis points (e.g. 1400 = 14.00%). Configured per env. */
    private int $guestFeeBps;

    /** Host service fee in basis points (e.g. 300 = 3.00%). */
    private int $hostFeeBps;

    /** Default tax rate in basis points. Real implementation would lookup by jurisdiction. */
    private int $defaultTaxBps;

    public function __construct()
    {
        $this->guestFeeBps   = (int) config('vaytoven.fees.guest_bps', 1400);
        $this->hostFeeBps    = (int) config('vaytoven.fees.host_bps', 300);
        $this->defaultTaxBps = (int) config('vaytoven.fees.default_tax_bps', 850);
    }

    /**
     * Build a complete price quote for a property + dates + guest count.
     *
     * @return array{
     *   nights: int,
     *   base_price_cents: int,
     *   cleaning_fee_cents: int,
     *   extra_guest_fee_cents: int,
     *   subtotal_cents: int,
     *   guest_service_fee_cents: int,
     *   host_service_fee_cents: int,
     *   tax_cents: int,
     *   total_cents: int,
     *   host_payout_cents: int,
     *   currency: string,
     * }
     */
    public function quote(
        Property $property,
        CarbonInterface $checkIn,
        CarbonInterface $checkOut,
        int $guests,
    ): array {
        $nights = $checkIn->diffInDays($checkOut);

        if ($nights < 1) {
            throw new InvalidArgumentException('Check-out must be after check-in.');
        }
        if ($nights < $property->min_nights) {
            throw new InvalidArgumentException("Minimum {$property->min_nights} nights for this property.");
        }
        if ($nights > $property->max_nights) {
            throw new InvalidArgumentException("Maximum {$property->max_nights} nights for this property.");
        }
        if ($guests < 1 || $guests > $property->max_guests) {
            throw new InvalidArgumentException("This property allows 1–{$property->max_guests} guests.");
        }

        // Base price = sum of nightly rates across the range, honouring per-day overrides
        $basePriceCents = 0;
        $period = CarbonPeriod::create($checkIn, $checkOut->copy()->subDay());
        $overrides = $property->calendar()
            ->whereBetween('date', [$checkIn, $checkOut->copy()->subDay()])
            ->whereNotNull('price_override_cents')
            ->pluck('price_override_cents', 'date');

        foreach ($period as $day) {
            $key = $day->toDateString();
            $basePriceCents += (int) ($overrides[$key] ?? $property->base_price_cents);
        }

        // Extra guest fee — only applies above the threshold
        $extraGuestFeeCents = 0;
        if ($property->extra_guests_after > 0
            && $property->extra_guest_fee_cents > 0
            && $guests > $property->extra_guests_after
        ) {
            $extraGuests = $guests - $property->extra_guests_after;
            $extraGuestFeeCents = $extraGuests * $property->extra_guest_fee_cents * $nights;
        }

        $cleaningFeeCents = (int) $property->cleaning_fee_cents;
        $subtotalCents = $basePriceCents + $cleaningFeeCents + $extraGuestFeeCents;

        // Fees calculated against subtotal in basis points to avoid float drift
        $guestServiceFeeCents = $this->bps($subtotalCents, $this->guestFeeBps);
        $hostServiceFeeCents  = $this->bps($subtotalCents, $this->hostFeeBps);

        // Taxes apply to (subtotal + guest service fee). Real implementation
        // would lookup by jurisdiction; for now we use a single default rate.
        $taxCents = $this->bps($subtotalCents + $guestServiceFeeCents, $this->defaultTaxBps);

        $totalCents = $subtotalCents + $guestServiceFeeCents + $taxCents;

        // Host gets subtotal minus host fee. Cleaning fee passes through to host.
        $hostPayoutCents = $subtotalCents - $hostServiceFeeCents;

        return [
            'nights'                  => $nights,
            'base_price_cents'        => $basePriceCents,
            'cleaning_fee_cents'      => $cleaningFeeCents,
            'extra_guest_fee_cents'   => $extraGuestFeeCents,
            'subtotal_cents'          => $subtotalCents,
            'guest_service_fee_cents' => $guestServiceFeeCents,
            'host_service_fee_cents'  => $hostServiceFeeCents,
            'tax_cents'               => $taxCents,
            'total_cents'             => $totalCents,
            'host_payout_cents'       => $hostPayoutCents,
            'currency'                => $property->currency,
        ];
    }

    /**
     * Calculate refund amount based on cancellation policy and time-to-checkin.
     */
    public function cancellationRefund(
        int $totalCents,
        string $policy,
        CarbonInterface $now,
        CarbonInterface $checkIn,
    ): int {
        $hoursUntilCheckIn = $now->diffInHours($checkIn, false);

        return match ($policy) {
            'flexible' => $hoursUntilCheckIn >= 24 ? $totalCents : 0,

            'moderate' => match (true) {
                $hoursUntilCheckIn >= 24 * 5 => $totalCents,
                $hoursUntilCheckIn >= 24     => intdiv($totalCents, 2),
                default                      => 0,
            },

            'strict' => match (true) {
                $hoursUntilCheckIn >= 24 * 7  => $totalCents,
                $hoursUntilCheckIn >= 24 * 2  => intdiv($totalCents, 2),
                default                       => 0,
            },

            'non_refundable' => 0,

            default => throw new InvalidArgumentException("Unknown policy: $policy"),
        };
    }

    /**
     * Multiply cents by basis points safely (no floats).
     */
    private function bps(int $cents, int $bps): int
    {
        return intdiv($cents * $bps + 5000, 10000); // round to nearest cent
    }
}
