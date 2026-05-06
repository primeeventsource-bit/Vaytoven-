<?php

namespace Database\Factories;

use App\Models\MessageThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'thread_id' => MessageThread::factory(),
            'sender_user_id' => User::factory(),
            'body' => fake()->sentence(),
            'occurred_at' => now(),
        ];
    }
}
