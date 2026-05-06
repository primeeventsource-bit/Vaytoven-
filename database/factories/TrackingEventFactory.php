<?php

namespace Database\Factories;

use App\Enums\Surface;
use Illuminate\Database\Eloquent\Factories\Factory;

class TrackingEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_type' => fake()->randomElement([
                'page_view', 'search_performed', 'booking_created',
                'message_sent', 'login_succeeded', 'profile_updated',
            ]),
            'actor_user_id' => null,
            'visitor_id' => fake()->uuid(),
            'surface' => Surface::Web->value,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'metadata' => ['path' => '/'.fake()->slug()],
            // event_uuid, occurred_at, parent_hash, current_hash are filled by the
            // model `creating` hook — leave null here.
        ];
    }
}
