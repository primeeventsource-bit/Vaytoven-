<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChargebackDisputeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'user_id' => User::factory(),
            'processor' => 'stripe',
            'external_dispute_id' => 'dp_'.fake()->unique()->bothify('?????????????'),
            'amount_cents' => fake()->numberBetween(5000, 100000),
            'reason' => 'fraudulent',
            'status' => 'needs_response',
            'evidence_due_by' => now()->addDays(7),
        ];
    }
}
