<?php

namespace App\Services\Fees;

use App\Enums\FeeStructure;

/**
 * The fee configuration that applies to one quote, already flattened to the
 * two rates that matter. Immutable — once resolved for a booking these values
 * are snapshotted onto the booking row and never recomputed.
 */
final class ResolvedServiceFee
{
    public function __construct(
        public readonly FeeStructure $structure,
        /** Host rate in basis points. 300 = 3%. */
        public readonly int $hostBps,
        /** Guest rate in basis points. 0 under Single-Fee. */
        public readonly int $guestBps,
        public readonly ?int $configId,
    ) {}

    /**
     * The guest's Vaytoven Service Fee on a given base.
     *
     * intdiv keeps the arithmetic integer end-to-end (NFR-1) and always
     * rounds toward zero, so the platform never over-charges a guest by a
     * rounding cent.
     */
    public function guestFeeCents(int $baseCents): int
    {
        return intdiv($baseCents * $this->guestBps, 10000);
    }

    /** The host's Vaytoven Service Fee on their gross earnings. */
    public function hostFeeCents(int $hostGrossCents): int
    {
        return intdiv($hostGrossCents * $this->hostBps, 10000);
    }

    /** What the host is left with before any other deductions. */
    public function hostNetCents(int $hostGrossCents): int
    {
        return $hostGrossCents - $this->hostFeeCents($hostGrossCents);
    }

    /** Human-readable rate, e.g. "15.5%". */
    public static function formatBps(int $bps): string
    {
        $whole = intdiv($bps, 100);
        $frac = $bps % 100;

        if ($frac === 0) {
            return $whole.'%';
        }

        // 1410 -> "14.1%", 1455 -> "14.55%"
        $decimals = $frac % 10 === 0 ? 1 : 2;

        return number_format($bps / 100, $decimals).'%';
    }

    public function hostRateLabel(): string
    {
        return self::formatBps($this->hostBps);
    }

    public function guestRateLabel(): string
    {
        return self::formatBps($this->guestBps);
    }
}
