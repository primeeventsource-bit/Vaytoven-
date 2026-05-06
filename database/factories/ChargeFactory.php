<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\PaymentIntent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Charge>
 */
class ChargeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'payment_intent_id' => PaymentIntent::factory(),
            'booking_id' => Booking::factory(),
            'processor' => 'stripe',
            'external_charge_id' => 'ch_'.fake()->unique()->bothify('?????????????'),
            'amount_cents' => fake()->numberBetween(5000, 100000),
            'currency' => 'USD',
            'captured' => true,
            'metadata' => [],
        ];
    }
}
