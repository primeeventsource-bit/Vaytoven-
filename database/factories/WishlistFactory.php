<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class WishlistFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Tropical', 'Ski trips', 'Bachelor weekend', 'Family vacations']),
            'is_private' => true,
        ];
    }
}
