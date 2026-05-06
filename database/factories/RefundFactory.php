<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Charge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Refund>
 */
class RefundFactory extends Factory
{
    public function definition(): array
    {
        return [
            'charge_id' => Charge::factory(),
            'booking_id' => Booking::factory(),
            'actor_user_id' => null,
            'processor' => 'stripe',
            'external_refund_id' => 're_'.fake()->unique()->bothify('?????????????'),
            'amount_cents' => fake()->numberBetween(1000, 50000),
            'reason' => 'requested_by_customer',
        ];
    }
}
