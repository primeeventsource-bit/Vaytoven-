<?php

namespace Tests\Feature\Rbac;

use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCatalog;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function seedRbac(): void
    {
        $this->seed(RbacSeeder::class);
        Role::bustCache();
    }

    // --- Catalog sync -----------------------------------------------------

    public function test_seeder_syncs_every_catalog_permission(): void
    {
        $this->seedRbac();

        $this->assertSame(
            count(PermissionCatalog::keys()),
            Permission::query()->count(),
        );

        foreach (PermissionCatalog::keys() as $key) {
            $this->assertDatabaseHas('permissions', ['key' => $key]);
        }
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seedRbac();
        $firstCount = Permission::query()->count();

        $this->seedRbac();

        $this->assertSame($firstCount, Permission::query()->count());
        $this->assertSame(9, Role::query()->where('is_system', true)->count());
    }

    // --- Resolution through the legacy role column ------------------------

    public function test_primary_role_column_resolves_to_the_matching_system_role(): void
    {
        $this->seedRbac();
        $user = User::factory()->create(['role' => UserRole::Host]);

        $this->assertTrue($user->hasRole('host'));
        $this->assertTrue($user->hasPermission('properties.edit'));
        $this->assertFalse($user->hasPermission('users.create'));
    }

    public function test_attached_roles_add_permissions_on_top_of_the_primary_role(): void
    {
        $this->seedRbac();
        $user = User::factory()->create(['role' => UserRole::Traveler]);

        $this->assertFalse($user->hasPermission('properties.publish'));

        $user->roles()->attach(Role::query()->where('key', 'property_manager')->firstOrFail());
        $user->forgetEffectiveRoles();

        $this->assertTrue($user->hasPermission('properties.publish'));
        // ...and gains nothing the attached role doesn't grant.
        $this->assertFalse($user->hasPermission('users.create'));
    }

    public function test_super_admin_bypasses_every_permission(): void
    {
        $this->seedRbac();
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        foreach (PermissionCatalog::keys() as $key) {
            $this->assertTrue($superAdmin->hasPermission($key), "super admin lacked {$key}");
        }
    }

    public function test_admin_holds_everything_except_role_management(): void
    {
        $this->seedRbac();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->assertTrue($admin->hasPermission('settings.edit'));
        $this->assertTrue($admin->hasPermission('users.create'));
        $this->assertTrue($admin->hasPermission('billing.processors'));

        $this->assertFalse($admin->hasPermission('roles.create'));
        $this->assertFalse($admin->hasPermission('roles.delete'));
    }

    // --- Fail-open fallback while unseeded --------------------------------

    public function test_permission_checks_fall_back_to_the_legacy_gate_while_unseeded(): void
    {
        // Deliberately NOT seeded — this is the mid-deploy window.
        $this->assertFalse(Role::configured());

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $traveler = User::factory()->create(['role' => UserRole::Traveler]);

        $this->assertTrue($admin->hasPermission('settings.edit'));
        $this->assertTrue($admin->hasPermission('roles.create'));
        $this->assertFalse($traveler->hasPermission('properties.view'));
    }

    // --- Route enforcement ------------------------------------------------

    public function test_role_without_a_module_permission_is_forbidden(): void
    {
        $this->seedRbac();
        $manager = User::factory()->create(['role' => UserRole::Traveler]);
        $manager->roles()->attach(Role::query()->where('key', 'property_manager')->firstOrFail());

        $this->actingAs($manager)->get('/admin/users')->assertForbidden();
        $this->actingAs($manager)->get('/admin/settings')->assertForbidden();
    }

    public function test_plain_admin_cannot_reach_role_management(): void
    {
        $this->seedRbac();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get('/admin/roles')->assertForbidden();
    }

    public function test_super_admin_can_reach_role_management(): void
    {
        $this->seedRbac();
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($superAdmin)->get('/admin/roles')->assertOk();
    }

    public function test_deactivated_user_cannot_use_their_permissions(): void
    {
        $this->seedRbac();
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'deactivated_at' => now(),
        ]);

        $this->actingAs($admin)->get('/admin/users')->assertForbidden();
    }

    // --- Escalation guards ------------------------------------------------

    public function test_super_admin_can_create_a_custom_role(): void
    {
        $this->seedRbac();
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($superAdmin)->post('/admin/roles', [
            'key' => 'regional_manager',
            'name' => 'Regional Manager',
            'description' => 'Regional oversight.',
            'level' => 45,
            'permissions' => ['properties.view', 'properties.edit', 'reports.view'],
        ])->assertRedirect(route('admin.roles.index'));

        $role = Role::query()->where('key', 'regional_manager')->firstOrFail();

        $this->assertFalse($role->is_system);
        $this->assertEqualsCanonicalizing(
            ['properties.view', 'properties.edit', 'reports.view'],
            $role->permissionKeys(),
        );
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'role.create']);
    }

    public function test_system_roles_cannot_be_deleted(): void
    {
        $this->seedRbac();
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $admin = Role::query()->where('key', 'admin')->firstOrFail();

        $this->actingAs($superAdmin)
            ->delete(route('admin.roles.destroy', $admin))
            ->assertStatus(422);

        $this->assertDatabaseHas('roles', ['key' => 'admin']);
    }

    public function test_a_role_still_assigned_to_users_cannot_be_deleted(): void
    {
        $this->seedRbac();
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $custom = Role::query()->create([
            'key' => 'temp_role', 'name' => 'Temp', 'level' => 5,
            'is_system' => false, 'is_super' => false,
        ]);
        User::factory()->create()->roles()->attach($custom);

        $this->actingAs($superAdmin)
            ->delete(route('admin.roles.destroy', $custom))
            ->assertRedirect(route('admin.roles.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('roles', ['key' => 'temp_role']);
    }

    public function test_an_unknown_permission_key_is_never_granted(): void
    {
        $this->seedRbac();
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($superAdmin)->post('/admin/roles', [
            'key' => 'sneaky',
            'name' => 'Sneaky',
            'level' => 10,
            'permissions' => ['properties.view', 'system.root'],
        ])->assertSessionHasErrors('permissions.1');

        $this->assertDatabaseMissing('roles', ['key' => 'sneaky']);
    }
}
