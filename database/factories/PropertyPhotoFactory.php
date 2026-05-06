<?php

namespace Database\Factories;

use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PropertyPhoto>
 */
class PropertyPhotoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'url' => fake()->imageUrl(1200, 800, 'cats'),
            'sort_order' => 0,
            'caption' => fake()->sentence(3),
        ];
    }
}
