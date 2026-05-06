<?php

namespace Database\Factories;

use App\Models\SupportChatSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupportMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'session_id' => SupportChatSession::factory(),
            'role' => 'user',
            'content' => fake()->sentence(),
            'occurred_at' => now(),
        ];
    }
}
