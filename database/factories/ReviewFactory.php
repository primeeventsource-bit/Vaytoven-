<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'property_id' => Property::factory(),
            'author_user_id' => User::factory(),
            'author_role' => 'traveler',
            'rating' => fake()->numberBetween(3, 5),
            'body' => fake()->paragraph(),
            'is_visible' => false,
        ];
    }
}
