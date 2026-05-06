<?php

namespace Database\Factories;

use App\Enums\Surface;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoginSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'auth_event' => 'login',
            'surface' => Surface::Web->value,
            'ip_address' => fake()->ipv4(),
            'country' => 'US',
            'region' => fake()->state(),
            'city' => fake()->city(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'device_type' => 'desktop',
            'os' => 'macOS',
            'browser' => 'Chrome',
            'user_agent' => fake()->userAgent(),
            'is_suspicious' => false,
            'occurred_at' => now(),
        ];
    }

    public function suspicious(array $reasons = ['new_country']): static
    {
        return $this->state(fn () => [
            'is_suspicious' => true,
            'suspicious_reasons' => $reasons,
        ]);
    }
}
