<?php

namespace Database\Factories;

use App\Models\SupportTicket;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupportTicketMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ticket_id' => SupportTicket::factory(),
            'sender_user_id' => null,
            'is_internal_note' => false,
            'body' => fake()->sentence(),
            'occurred_at' => now(),
        ];
    }
}
