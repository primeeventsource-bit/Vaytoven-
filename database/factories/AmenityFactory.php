<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Amenity>
 */
class AmenityFactory extends Factory
{
    public function definition(): array
    {
        $slug = fake()->unique()->slug(2);

        return [
            'slug' => $slug,
            'label' => ucwords(str_replace('-', ' ', $slug)),
            'category' => fake()->randomElement([
                'safety', 'accessibility', 'outdoor', 'indoor', 'family', 'workspace', 'other',
            ]),
            'icon' => null,
        ];
    }
}
