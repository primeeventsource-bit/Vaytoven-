<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\MembersEnquiry;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        $u = User::factory()->create();
        $u->roles()->attach($role->id);
        return $u->fresh();
    }

    public function test_status_transition_writes_audit_row(): void
    {
        $admin = $this->admin();
        $enquiry = MembersEnquiry::factory()->create(['status' => 'new']);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/admin/members-enquiries/{$enquiry->id}", [
                'status' => 'qualified',
            ])
            ->assertOk();

        $log = AdminAuditLog::where('action', 'members_enquiry.qualified')->firstOrFail();
        $this->assertEquals($admin->id, $log->admin_user_id);
        $this->assertEquals('members_enquiries', $log->target_type);
        $this->assertEquals($enquiry->id, $log->target_uuid);
        $this->assertSame('new', $log->before_state['status']);
        $this->assertSame('qualified', $log->after_state['status']);
    }

    public function test_reject_audit_includes_reason(): void
    {
        $admin = $this->admin();
        $enquiry = MembersEnquiry::factory()->create(['status' => 'contacted']);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/admin/members-enquiries/{$enquiry->id}", [
                'status'           => 'rejected',
                'rejection_reason' => 'Property outside active markets',
            ])
            ->assertOk();

        $log = AdminAuditLog::where('action', 'members_enquiry.rejected')->firstOrFail();
        $this->assertSame('Property outside active markets', $log->reason);
    }

    public function test_assign_writes_audit_row(): void
    {
        $admin   = $this->admin();
        $enquiry = MembersEnquiry::factory()->create(['assigned_to' => null]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/members-enquiries/{$enquiry->id}/assign")
            ->assertOk();

        $log = AdminAuditLog::where('action', 'members_enquiry.assigned')->firstOrFail();
        $this->assertSame('self-assigned', $log->reason);
    }

    public function test_audit_logs_endpoint_filters_by_target(): void
    {
        $admin = $this->admin();
        $a = MembersEnquiry::factory()->create();
        $b = MembersEnquiry::factory()->create();

        // Two transitions on a, one on b
        $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/admin/members-enquiries/{$a->id}", ['status' => 'contacted'])->assertOk();
        $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/admin/members-enquiries/{$a->id}", ['status' => 'qualified'])->assertOk();
        $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/admin/members-enquiries/{$b->id}", ['status' => 'contacted'])->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/admin/members-enquiries/{$a->id}/audit")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_audit_action_prefix_filter(): void
    {
        $admin = $this->admin();
        $enquiry = MembersEnquiry::factory()->create();

        $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/admin/members-enquiries/{$enquiry->id}", ['status' => 'contacted'])->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/admin/members-enquiries/{$enquiry->id}/assign")->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/audit-logs?action=members_enquiry.')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_sanitiser_strips_password_hash(): void
    {
        $admin = $this->admin();
        $enquiry = MembersEnquiry::factory()->create();

        $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/admin/members-enquiries/{$enquiry->id}", ['status' => 'contacted'])->assertOk();

        $log = AdminAuditLog::firstOrFail();
        // sanitise() should never echo back any of the stripped keys, even if upstream snapshot included them
        foreach (['password_hash', 'remember_token', 'updated_at'] as $key) {
            $this->assertArrayNotHasKey($key, $log->before_state ?? []);
            $this->assertArrayNotHasKey($key, $log->after_state ?? []);
        }
    }
}
