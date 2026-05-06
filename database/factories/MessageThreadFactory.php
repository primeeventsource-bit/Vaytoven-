<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageThreadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'booking_id' => null,
            'traveler_id' => User::factory(),
            'host_id' => User::factory(),
            'last_message_at' => now(),
        ];
    }
}
