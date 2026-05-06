<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Enums\CancellationPolicy;
use App\Models\Booking;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Booking>
 */
class BookingFactory extends Factory
{
    public function definition(): array
    {
        $checkIn = fake()->dateTimeBetween('+1 week', '+3 months');
        $nights = fake()->numberBetween(2, 7);
        $checkOut = (clone $checkIn)->modify("+{$nights} days");
        $rate = fake()->numberBetween(8000, 50000);
        $cleaning = fake()->numberBetween(0, 10000);
        $serviceFee = (int) round(($rate * $nights) * 0.12);
        $tax = (int) round(($rate * $nights + $cleaning + $serviceFee) * 0.08);
        $total = $rate * $nights + $cleaning + $serviceFee + $tax;

        return [
            'property_id' => Property::factory(),
            'traveler_id' => User::factory(),
            // Booking::generateConfirmationCode() runs in `creating` hook; leaving null here is fine.
            'check_in_date' => $checkIn->format('Y-m-d'),
            'check_out_date' => $checkOut->format('Y-m-d'),
            'guests' => fake()->numberBetween(1, 4),
            'nightly_rate_cents' => $rate,
            'nights' => $nights,
            'subtotal_cents' => $rate * $nights,
            'cleaning_fee_cents' => $cleaning,
            'service_fee_cents' => $serviceFee,
            'tax_cents' => $tax,
            'total_cents' => $total,
            'cancellation_policy' => CancellationPolicy::Moderate->value,
            'status' => BookingStatus::PendingPayment->value,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['status' => BookingStatus::Confirmed->value]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::Cancelled->value,
            'cancelled_at' => now(),
            'cancelled_reason' => 'traveler_cancelled',
        ]);
    }
}
