<?php

namespace Tests\Unit;

use App\Enums\CancellationPolicy;
use App\Models\Booking;
use App\Services\Payments\RefundCalculator;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * Pure-function refund math. No DB, no models created — uses an in-memory
 * Booking instance with attributes populated. Tests cover all 4 policies
 * across the relevant time-from-checkin boundaries.
 */
class RefundCalculatorTest extends TestCase
{
    private function bookingWith(
        CancellationPolicy $policy,
        int $subtotal = 30000,
        int $cleaning = 5000,
        int $serviceFee = 4200,
        int $tax = 3140,
        string $checkIn = '2027-01-15',
    ): Booking {
        $booking = new Booking();
        $booking->cancellation_policy = $policy;
        $booking->check_in_date = $checkIn;
        $booking->subtotal_cents = $subtotal;
        $booking->cleaning_fee_cents = $cleaning;
        $booking->service_fee_cents = $serviceFee;
        $booking->tax_cents = $tax;

        return $booking;
    }

    // ============================================================
    // Flexible
    // ============================================================

    public function test_flexible_full_refund_25h_before(): void
    {
        $booking = $this->bookingWith(CancellationPolicy::Flexible);
        $cancelledAt = CarbonImmutable::parse('2027-01-13 23:00');

        $result = RefundCalculator::compute($booking, $cancelledAt);

        $this->assertSame('full', $result->tier);
        $this->assertSame(30000, $result->subtotal_refund_cents);
        $this->assertSame(5000, $result->cleaning_refund_cents);
        $this->assertSame(0, $result->service_fee_refund_cents);
        $this->assertSame(3140, $result->tax_refund_cents);
        $this->assertSame(38140, $result->total_cents);
    }

    public function test_flexible_no_refund_23h_before(): void
    {
        $booking = $this->bookingWith(CancellationPolicy::Flexible);
        $cancelledAt = CarbonImmutable::parse('2027-01-14 01:00'); // 23h before check-in

        $result = RefundCalculator::compute($booking, $cancelledAt);

        $this->assertSame('none', $result->tier);
        $this->assertSame(0, $result->total_cents);
    }

    // ============================================================
    // Moderate
    // ============================================================

    public function test_moderate_full_refund_6_days_before(): void
    {
        $booking = $this->bookingWith(CancellationPolicy::Moderate);
        $cancelledAt = CarbonImmutable::parse('2027-01-09 00:00'); // 6 days before

        $result = RefundCalculator::compute($booking, $cancelledAt);

        $this->assertSame('full', $result->tier);
        $this->assertSame(30000, $result->subtotal_refund_cents);
        $this->assertSame(5000, $result->cleaning_refund_cents);
        $this->assertSame(38140, $result->total_cents);
    }

    public function test_moderate_50pct_refund_4_days_before(): void
    {
        $booking = $this->bookingWith(CancellationPolicy::Moderate);
        $cancelledAt = CarbonImmutable::parse('2027-01-11 00:00'); // 4 days before

        $result = RefundCalculator::compute($booking, $cancelledAt);

        $this->assertSame('partial', $result->tier);
        $this->assertSame(15000, $result->subtotal_refund_cents);  // 50%
        $this->assertSame(2500, $result->cleaning_refund_cents);    // 50%
        $this->assertSame(0, $result->service_fee_refund_cents);    // never refunded
        $this->assertSame(1570, $result->tax_refund_cents);         // 50%
        $this->assertSame(19070, $result->total_cents);
    }

    public function test_moderate_no_refund_within_24h(): void
    {
        $booking = $this->bookingWith(CancellationPolicy::Moderate);
        $cancelledAt = CarbonImmutable::parse('2027-01-14 12:00'); // 12h before

        $result = RefundCalculator::compute($booking, $cancelledAt);

        $this->assertSame('none', $result->tier);
        $this->assertSame(0, $result->total_cents);
    }

    public function test_moderate_exactly_5_day_boundary_is_full(): void
    {
        $booking = $this->bookingWith(CancellationPolicy::Moderate);
        // Exactly 5 days (120h) before
        $cancelledAt = CarbonImmutable::parse('2027-01-10 00:00');

        $result = RefundCalculator::compute($booking, $cancelledAt);

        $this->assertSame('full', $result->tier);
    }

    public function test_moderate_exactly_24h_boundary_is_partial(): void
    {
        $booking = $this->bookingWith(CancellationPolicy::Moderate);
        $cancelledAt = CarbonImmutable::parse('2027-01-14 00:00'); // exactly 24h

        $result = RefundCalculator::compute($booking, $cancelledAt);

        $this->assertSame('partial', $result->tier);
    }

    // ============================================================
    // Strict
    // ============================================================

    public function test_strict_50pct_refund_8_days_before(): void
    {
        $booking = $this->bookingWith(CancellationPolicy::Strict);
        $cancelledAt = CarbonImmutable::parse('2027-01-07 00:00'); // 8 days before

        $result = RefundCalculator::compute($booking, $cancelledAt);

        $this->assertSame('partial', $result->tier);
        $this->assertSame(15000, $result->subtotal_refund_cents);
    }

    public function test_strict_no_refund_6_days_before(): void
    {
        $booking = $this->bookingWith(CancellationPolicy::Strict);
        $cancelledAt = CarbonImmutable::parse('2027-01-09 00:00'); // 6 days before

        $result = RefundCalculator::compute($booking, $cancelledAt);

        $this->assertSame('none', $result->tier);
        $this->assertSame(0, $result->total_cents);
    }

    public function test_strict_exactly_7_day_boundary_is_partial(): void
    {
        $booking = $this->bookingWith(CancellationPolicy::Strict);
        $cancelledAt = CarbonImmutable::parse('2027-01-08 00:00'); // exactly 7 days

        $result = RefundCalculator::compute($booking, $cancelledAt);

        $this->assertSame('partial', $result->tier);
    }

    // ============================================================
    // Non-refundable
    // ============================================================

    public function test_non_refundable_returns_zero_even_far_in_advance(): void
    {
        $booking = $this->bookingWith(CancellationPolicy::NonRefundable);
        $cancelledAt = CarbonImmutable::parse('2027-01-01 00:00'); // 14 days before

        $result = RefundCalculator::compute($booking, $cancelledAt);

        $this->assertSame('none', $result->tier);
        $this->assertSame(0, $result->total_cents);
    }

    // ============================================================
    // Invariants
    // ============================================================

    public function test_service_fee_is_never_refunded_to_traveler(): void
    {
        foreach (CancellationPolicy::cases() as $policy) {
            $booking = $this->bookingWith($policy);
            $cancelledAt = CarbonImmutable::parse('2027-01-01 00:00'); // far in advance

            $result = RefundCalculator::compute($booking, $cancelledAt);

            $this->assertSame(
                0,
                $result->service_fee_refund_cents,
                "Service fee should not refund for policy {$policy->value}"
            );
        }
    }

    public function test_money_is_always_integer_cents_no_floats(): void
    {
        $booking = $this->bookingWith(
            CancellationPolicy::Moderate,
            subtotal: 13337, // odd amount that won't divide evenly
            cleaning: 7771,
            tax: 1234,
        );
        $cancelledAt = CarbonImmutable::parse('2027-01-12 00:00'); // 3 days = partial 50%

        $result = RefundCalculator::compute($booking, $cancelledAt);

        $this->assertIsInt($result->subtotal_refund_cents);
        $this->assertIsInt($result->cleaning_refund_cents);
        $this->assertIsInt($result->tax_refund_cents);
        $this->assertIsInt($result->total_cents);
        $this->assertSame(6669, $result->subtotal_refund_cents);   // round(13337*0.5) = 6669 (banker's rounds 0.5 to even, but PHP round defaults to away-from-zero so 6668.5 -> 6669)
    }
}
