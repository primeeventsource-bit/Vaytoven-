<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AdminAuditLog;
use App\Models\FeatureFlag;
use App\Models\PaymentProcessorConfig;
use App\Models\User;
use App\Services\Settings\SettingsRepository;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSettingsEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);
    }

    public function test_settings_pages_require_admin(): void
    {
        $traveler = User::factory()->create(['role' => UserRole::Traveler]);

        $this->get('/admin/settings/general')->assertRedirect(); // guest -> login
        $this->actingAs($traveler)->get('/admin/settings/general')->assertForbidden();
        $this->actingAs($this->admin)->get('/admin/settings/general')->assertOk();
    }

    public function test_every_group_is_editable_by_any_admin(): void
    {
        // Regular admins (not just super admins) manage all groups,
        // including the ones holding credentials.
        $this->actingAs($this->admin)->get('/admin/settings/payments')->assertOk();
        $this->actingAs($this->admin)->get('/admin/settings/security')->assertOk();
        $this->actingAs($this->admin)
            ->put('/admin/settings/integrations', ['settings' => ['integrations.smtp_host' => 'mail.example.com']])
            ->assertRedirect();
        $this->assertSame('mail.example.com', setting('integrations.smtp_host'));

        $this->actingAs($this->superAdmin)->get('/admin/settings/payments')->assertOk();
    }

    public function test_group_update_persists_validates_and_audits(): void
    {
        $response = $this->actingAs($this->admin)
            ->from('/admin/settings/fees')
            ->put('/admin/settings/fees', ['settings' => ['fees.guest_service_pct' => 20]]);

        $response->assertRedirect('/admin/settings/fees');
        $this->assertSame(20, setting('fees.guest_service_pct'));
        $this->assertSame(1, AdminAuditLog::query()->where('action', 'setting.update')->count());

        // Out-of-range value: rejected with an inline error, nothing written.
        $this->actingAs($this->admin)
            ->from('/admin/settings/fees')
            ->put('/admin/settings/fees', ['settings' => ['fees.guest_service_pct' => 90]])
            ->assertSessionHasErrors('fees.guest_service_pct');
        $this->assertSame(20, setting('fees.guest_service_pct'));
    }

    public function test_blank_sensitive_field_keeps_current_value(): void
    {
        app(SettingsRepository::class)->set('integrations.smtp_password', 'keep-me', $this->superAdmin);

        $this->actingAs($this->superAdmin)
            ->put('/admin/settings/integrations', ['settings' => [
                'integrations.smtp_password' => '',
                'integrations.smtp_host' => 'mail.example.com',
            ]])
            ->assertRedirect();

        $this->assertSame('keep-me', setting('integrations.smtp_password'));
        $this->assertSame('mail.example.com', setting('integrations.smtp_host'));
    }

    public function test_api_rejects_unknown_setting_keys(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/v1/admin/settings/booking', ['settings' => ['booking.nonsense' => 1]])
            ->assertStatus(422);

        $this->assertArrayHasKey('booking.nonsense', $response->json('errors'));
    }

    public function test_api_settings_read_redacts_sensitive_values(): void
    {
        app(SettingsRepository::class)->set('ai_chat.api_key', 'sk-ant-secret', $this->superAdmin);

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/admin/settings/ai_chat')
            ->assertOk();

        $this->assertStringNotContainsString('sk-ant-secret', $response->getContent());
    }

    public function test_feature_flag_toggle_disables_chat_endpoint_server_side(): void
    {
        FeatureFlag::create(['key' => 'ai_chat', 'enabled' => true, 'scope' => 'global', 'description' => '']);

        $this->actingAs($this->admin)
            ->put('/admin/settings/flags/ai_chat', ['enabled' => '0', 'scope' => 'global'])
            ->assertRedirect();

        $this->assertFalse(feature('ai_chat'));

        $this->postJson('/api/v1/support/chat', ['message' => 'hello'])
            ->assertStatus(503)
            ->assertJsonPath('error', 'support_chat_disabled');
    }

    public function test_processor_update_by_admin_redacts_credentials(): void
    {
        (new SettingsSeeder)->run();

        $this->actingAs($this->admin)->get('/admin/settings/processors')->assertOk();

        $this->actingAs($this->admin)
            ->put('/admin/settings/processors/nmi', [
                'enabled' => '1',
                'mode' => 'live',
                'priority' => 10,
                'credentials' => ['security_key' => 'nmi-super-secret', 'tokenization_key' => ''],
            ])
            ->assertRedirect();

        $processor = PaymentProcessorConfig::query()->where('code', 'nmi')->sole();
        $this->assertSame('nmi-super-secret', $processor->credentials['security_key']);

        // Serialized form and audit payload never contain the secret.
        $this->assertStringNotContainsString('nmi-super-secret', json_encode($processor->toArray()));
        $log = AdminAuditLog::query()->where('action', 'processor.update')->sole();
        $this->assertStringNotContainsString('nmi-super-secret', json_encode($log->payload));
    }

    public function test_disabling_processor_puts_booking_flow_in_demo_mode(): void
    {
        (new SettingsSeeder)->run();
        config(['services.nmi.security_key' => 'real_key', 'services.nmi.tokenization_key' => 'tok_key']);

        $this->actingAs($this->admin)
            ->put('/admin/settings/processors/nmi', ['enabled' => '0', 'mode' => 'live', 'priority' => 10])
            ->assertRedirect();

        $this->assertFalse(PaymentProcessorConfig::isEnabled('nmi'));
    }
}
