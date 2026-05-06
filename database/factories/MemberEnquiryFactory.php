<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class MemberEnquiryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'club' => fake()->randomElement([
                'Marriott Vacation Club', 'Hilton Grand Vacations', 'Disney Vacation Club',
                'RCI Points', 'Interval International', 'Other',
            ]),
            'property' => fake()->city().' Resort',
            'points' => (string) fake()->numberBetween(50000, 500000),
            'contact_window' => fake()->randomElement(['mornings', 'afternoons', 'evenings', null]),
            'consented_at' => now(),
            'source_url' => 'https://vaytoven.com/',
            'ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
