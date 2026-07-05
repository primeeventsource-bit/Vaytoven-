<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AdminAuditLog;
use App\Models\FeatureFlag;
use App\Models\User;
use App\Services\Settings\FeatureFlagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureFlagServiceTest extends TestCase
{
    use RefreshDatabase;

    private FeatureFlagService $flags;

    protected function setUp(): void
    {
        parent::setUp();
        $this->flags = app(FeatureFlagService::class);
    }

    public function test_missing_flag_resolves_to_default(): void
    {
        $this->assertTrue($this->flags->enabled('does_not_exist'));
        $this->assertFalse($this->flags->enabled('does_not_exist', default: false));
        $this->assertTrue(feature('does_not_exist'));
    }

    public function test_disabled_flag_is_off_and_toggle_busts_cache(): void
    {
        FeatureFlag::create(['key' => 'ai_chat', 'enabled' => true, 'scope' => 'global']);
        $this->assertTrue(feature('ai_chat')); // warms the cache

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->flags->update('ai_chat', ['enabled' => false], $admin, '203.0.113.9');

        $this->assertFalse(feature('ai_chat'));

        $log = AdminAuditLog::query()->where('action', 'flag.toggle')->sole();
        $this->assertSame('ai_chat', $log->payload['key']);
        $this->assertTrue($log->payload['old_value']['enabled']);
        $this->assertFalse($log->payload['new_value']['enabled']);
    }

    public function test_role_scope_matches_user_role(): void
    {
        FeatureFlag::create(['key' => 'host_tools', 'enabled' => true, 'scope' => 'role', 'scope_value' => 'host']);

        $host = User::factory()->create(['role' => UserRole::Host]);
        $traveler = User::factory()->create(['role' => UserRole::Traveler]);

        $this->assertTrue($this->flags->enabled('host_tools', $host));
        $this->assertFalse($this->flags->enabled('host_tools', $traveler));
        // Anonymous visitors count as travelers.
        $this->assertFalse($this->flags->enabled('host_tools'));
    }

    public function test_rollout_pct_extremes(): void
    {
        FeatureFlag::create(['key' => 'zero', 'enabled' => true, 'rollout_pct' => 0]);
        FeatureFlag::create(['key' => 'full', 'enabled' => true, 'rollout_pct' => 100]);

        $user = User::factory()->create();
        $this->assertFalse($this->flags->enabled('zero', $user));
        $this->assertTrue($this->flags->enabled('full', $user));
    }

    public function test_rollout_bucket_is_deterministic_per_user(): void
    {
        FeatureFlag::create(['key' => 'half', 'enabled' => true, 'rollout_pct' => 50]);
        $user = User::factory()->create();

        $first = $this->flags->enabled('half', $user);
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame($first, $this->flags->enabled('half', $user));
        }
    }
}
