<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_users_redirect_to_login(): void
    {
        $this->get('/admin/contracts')
            ->assertRedirect('/login');
    }

    public function test_authenticated_traveler_gets_403(): void
    {
        $traveler = User::factory()->create(['role' => UserRole::Traveler]);

        $this->actingAs($traveler)
            ->get('/admin/contracts')
            ->assertForbidden();
    }

    public function test_authenticated_host_gets_403(): void
    {
        $host = User::factory()->create(['role' => UserRole::Host]);

        $this->actingAs($host)
            ->get('/admin/contracts')
            ->assertForbidden();
    }

    public function test_authenticated_member_gets_403(): void
    {
        $member = User::factory()->create(['role' => UserRole::Member]);

        $this->actingAs($member)
            ->get('/admin/contracts')
            ->assertForbidden();
    }

    public function test_admin_role_passes_middleware(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->get('/admin/contracts');

        // 200 if the controller resolves; not 403 (middleware passed).
        $this->assertNotEquals(403, $response->status());
    }

    public function test_super_admin_role_passes_middleware(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $response = $this->actingAs($superAdmin)->get('/admin/contracts');

        $this->assertNotEquals(403, $response->status());
    }

    public function test_role_enum_helpers(): void
    {
        $this->assertTrue(UserRole::Admin->isAdmin());
        $this->assertTrue(UserRole::SuperAdmin->isAdmin());
        $this->assertFalse(UserRole::Traveler->isAdmin());
        $this->assertFalse(UserRole::Host->isAdmin());
        $this->assertFalse(UserRole::Member->isAdmin());

        $this->assertTrue(UserRole::SuperAdmin->isSuperAdmin());
        $this->assertFalse(UserRole::Admin->isSuperAdmin());
    }
}
