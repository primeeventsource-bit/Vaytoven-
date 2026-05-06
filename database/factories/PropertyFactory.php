<?php

namespace Database\Factories;

use App\Enums\CancellationPolicy;
use App\Enums\PropertyStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Property>
 */
class PropertyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'host_id' => User::factory()->state(['role' => UserRole::Host]),
            'listing_source' => 'host',
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'address_line' => fake()->streetAddress(),
            'city' => fake()->city(),
            'region' => fake()->state(),
            'country' => fake()->countryCode(),
            'postal_code' => fake()->postcode(),
            'capacity' => fake()->numberBetween(1, 12),
            'bedrooms' => fake()->numberBetween(1, 5),
            'beds' => fake()->numberBetween(1, 6),
            'bathrooms' => fake()->randomElement([1.0, 1.5, 2.0, 2.5, 3.0]),
            'base_nightly_cents' => fake()->numberBetween(8000, 80000), // $80–$800
            'cleaning_fee_cents' => fake()->numberBetween(0, 15000),
            'cancellation_policy' => fake()->randomElement(CancellationPolicy::cases())->value,
            'minimum_nights' => fake()->numberBetween(1, 7),
            'status' => PropertyStatus::Active->value,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => PropertyStatus::Draft->value]);
    }

    public function managed(): static
    {
        return $this->state(fn () => ['listing_source' => 'managed']);
    }
}
