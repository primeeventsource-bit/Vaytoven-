<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Settings\SettingsRepository;
use App\Services\Settings\SettingsSchema;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSettingsEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_exposes_only_public_keys(): void
    {
        (new SettingsSeeder)->run();

        $response = $this->getJson('/api/v1/settings/public')->assertOk();
        $payload = $response->json();

        $this->assertSame('Vaytoven Rentals', $payload['settings']['general.site_name']);
        $this->assertArrayHasKey('ai_chat', $payload['features']);

        foreach (array_keys($payload['settings']) as $key) {
            $spec = SettingsSchema::spec($key);
            $this->assertTrue($spec['public'], "{$key} leaked into the public bootstrap");
            $this->assertFalse($spec['sensitive'], "{$key} is sensitive and must never be public");
        }

        // Processor gates are operational detail — not exposed.
        foreach (array_keys($payload['features']) as $flag) {
            $this->assertStringStartsNotWith('processor.', $flag);
        }
    }

    public function test_no_secret_material_in_public_payload(): void
    {
        (new SettingsSeeder)->run();

        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        app(SettingsRepository::class)->set('integrations.smtp_password', 'super-secret-pass', $admin);
        app(SettingsRepository::class)->set('ai_chat.api_key', 'sk-ant-xyz', $admin);

        $body = $this->getJson('/api/v1/settings/public')->assertOk()->getContent();

        $this->assertStringNotContainsString('super-secret-pass', $body);
        $this->assertStringNotContainsString('sk-ant-xyz', $body);
        $this->assertStringNotContainsString('smtp_password', $body);
    }

    public function test_maintenance_mode_gates_site_but_not_admins(): void
    {
        (new SettingsSeeder)->run();

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        app(SettingsRepository::class)->set('general.maintenance_mode', true, $admin);
        app(SettingsRepository::class)->set('general.maintenance_message', 'Back at noon.', $admin);

        $this->get('/properties')->assertStatus(503)->assertSee('Back at noon.');
        $this->get('/login')->assertOk(); // ops can still sign in
        $this->actingAs($admin)->get('/properties')->assertOk();

        app(SettingsRepository::class)->set('general.maintenance_mode', false, $admin);
        $this->get('/properties')->assertOk();
    }
}
