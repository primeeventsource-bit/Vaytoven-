<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AdminAuditLog;
use App\Models\Property;
use App\Models\User;
use App\Services\AdminAuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_log_records_actor_action_and_subject(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $property = Property::factory()->create();

        $entry = AdminAuditLogService::log(
            actor: $admin,
            action: 'property.approve',
            subject: $property,
            payload: ['note' => 'Looks legit'],
            ipAddress: '198.51.100.42',
        );

        $this->assertSame($admin->id, $entry->actor_user_id);
        $this->assertSame('property.approve', $entry->action);
        $this->assertSame(Property::class, $entry->subject_type);
        $this->assertSame($property->id, $entry->subject_id);
        $this->assertSame('Looks legit', $entry->payload['note']);
        $this->assertSame('198.51.100.42', $entry->ip_address);
    }

    public function test_log_handles_subjectless_actions(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $entry = AdminAuditLogService::log(
            actor: $admin,
            action: 'system.purge_idle_sessions',
            payload: ['count' => 142],
        );

        $this->assertNull($entry->subject_type);
        $this->assertNull($entry->subject_id);
        $this->assertSame(142, $entry->payload['count']);
    }

    public function test_morph_to_subject_resolves_to_actual_model(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $property = Property::factory()->create();

        AdminAuditLogService::log($admin, 'property.archive', $property);

        $entry = AdminAuditLog::first();
        $this->assertTrue($entry->subject->is($property));
    }

    public function test_actor_relationship_links_back_to_user(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        AdminAuditLogService::log($admin, 'user.suspend');

        $entry = AdminAuditLog::first();
        $this->assertTrue($entry->actor->is($admin));
    }
}
