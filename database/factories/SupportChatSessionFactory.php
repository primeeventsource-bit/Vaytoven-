<?php

namespace Database\Factories;

use App\Enums\Surface;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupportChatSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => null,
            'visitor_id' => fake()->uuid(),
            'surface' => Surface::Web->value,
            'claude_model' => 'claude-sonnet-4-6',
            'system_prompt_version' => 'v1',
            'started_at' => now(),
            'metadata' => [],
        ];
    }
}
