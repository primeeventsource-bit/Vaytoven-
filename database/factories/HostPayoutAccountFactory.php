<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HostPayoutAccount>
 */
class HostPayoutAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'host_id' => User::factory()->state(['role' => UserRole::Host]),
            'processor' => 'stripe',
            'external_account_id' => 'acct_'.fake()->unique()->bothify('????????????'),
            'status' => 'verified',
            'payouts_enabled' => true,
            'charges_enabled' => true,
            'last_synced_at' => now(),
            'metadata' => [],
        ];
    }
}
