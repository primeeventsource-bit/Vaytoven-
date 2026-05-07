<?php

namespace Database\Factories;

use App\Models\Subscriber;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'full_name'  => fake()->name(),
            'email'      => fake()->unique()->safeEmail(),
            'phone'      => fake()->phoneNumber(),
            'status'     => Subscriber::STATUS_ACTIVE,
            'source_url' => 'https://www.vaytoven.com/signup',
            'ip'         => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
