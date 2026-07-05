<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AdminAuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SettingsRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private SettingsRepository $repo;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = app(SettingsRepository::class);
        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
    }

    public function test_get_returns_schema_default_when_unset(): void
    {
        $this->assertSame(12, $this->repo->get('fees.guest_service_pct'));
        $this->assertSame(false, $this->repo->get('general.maintenance_mode'));
        $this->assertSame('Vaytoven Rentals', $this->repo->get('general.site_name'));
    }

    public function test_set_then_get_roundtrips_typed_values(): void
    {
        $this->repo->set('fees.guest_service_pct', 15, $this->admin);
        $this->assertSame(15, $this->repo->get('fees.guest_service_pct'));

        $this->repo->set('general.maintenance_mode', true, $this->admin);
        $this->assertTrue($this->repo->get('general.maintenance_mode'));

        $this->repo->set('mlp.enquiry_required_fields', ['name', 'email'], $this->admin);
        $this->assertSame(['name', 'email'], $this->repo->get('mlp.enquiry_required_fields'));
    }

    public function test_set_busts_cache_immediately(): void
    {
        // Warm the cache with the default...
        $this->assertSame(12, $this->repo->get('fees.guest_service_pct'));
        // ...then write and confirm the next read reflects it (no TTL wait).
        $this->repo->set('fees.guest_service_pct', 18, $this->admin);
        $this->assertSame(18, $this->repo->get('fees.guest_service_pct'));
        $this->assertSame(18, setting('fees.guest_service_pct'));
    }

    public function test_unknown_key_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->repo->set('fees.made_up_key', 10, $this->admin);
    }

    public function test_rule_violation_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->repo->set('fees.guest_service_pct', 90, $this->admin); // between:0,50
    }

    public function test_every_write_produces_exactly_one_audit_row(): void
    {
        $this->repo->set('booking.min_nights', 2, $this->admin, '203.0.113.7');

        $logs = AdminAuditLog::query()->where('action', 'setting.update')->get();
        $this->assertCount(1, $logs);
        $this->assertSame($this->admin->id, $logs[0]->actor_user_id);
        $this->assertSame('booking.min_nights', $logs[0]->payload['key']);
        $this->assertNull($logs[0]->payload['old_value']);
        $this->assertSame('2', $logs[0]->payload['new_value']);
        $this->assertSame('203.0.113.7', $logs[0]->ip_address);
    }

    public function test_encrypted_value_roundtrips_but_never_stored_or_audited_in_plaintext(): void
    {
        $secret = 'smtp-secret-123';
        $this->repo->set('integrations.smtp_password', $secret, $this->admin);

        // Reads decrypt transparently.
        $this->assertSame($secret, $this->repo->get('integrations.smtp_password'));

        // At rest: ciphertext, not plaintext.
        $raw = DB::table('settings')->where('key', 'integrations.smtp_password')->value('value');
        $this->assertNotNull($raw);
        $this->assertStringNotContainsString($secret, $raw);

        // Audit: redacted, not plaintext.
        $log = AdminAuditLog::query()->where('action', 'setting.update')->latest('id')->first();
        $this->assertSame(Setting::REDACTED, $log->payload['new_value']);
        $this->assertStringNotContainsString($secret, json_encode($log->payload));

        // Serialization: redacted.
        $row = Setting::query()->where('key', 'integrations.smtp_password')->first();
        $this->assertSame(Setting::REDACTED, $row->toArray()['value']);
    }

    public function test_group_for_display_redacts_sensitive_values(): void
    {
        $this->repo->set('integrations.smtp_password', 'hunter2', $this->admin);

        $fields = $this->repo->groupForDisplay('integrations');
        $this->assertSame(Setting::REDACTED, $fields['integrations.smtp_password']['value']);
        $this->assertTrue($fields['integrations.smtp_password']['has_value']);
        $this->assertStringNotContainsString('hunter2', json_encode($fields));
    }
}
