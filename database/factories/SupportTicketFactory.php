<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SupportTicketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'session_id' => null,
            'opened_by_user_id' => null,
            'subject' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'status' => 'open',
            'priority' => 'normal',
        ];
    }
}
