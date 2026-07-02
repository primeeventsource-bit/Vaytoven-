<?php

namespace Database\Factories;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaymentIntent>
 */
class PaymentIntentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'processor' => 'nmi',
            'external_intent_id' => 'booking:VYT-'.fake()->unique()->bothify('########'),
            'amount_cents' => fake()->numberBetween(5000, 100000),
            'currency' => 'USD',
            'status' => 'processing',
            'metadata' => [],
        ];
    }
}
