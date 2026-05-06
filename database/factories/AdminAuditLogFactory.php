<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AdminAuditLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'actor_user_id' => User::factory(),
            'action' => fake()->randomElement([
                'user.suspend', 'user.restore', 'enquiry.transition',
                'property.approve', 'property.archive', 'review.hide',
            ]),
            'subject_type' => null,
            'subject_id' => null,
            'payload' => ['note' => fake()->sentence()],
            'ip_address' => fake()->ipv4(),
            'occurred_at' => now(),
        ];
    }
}
