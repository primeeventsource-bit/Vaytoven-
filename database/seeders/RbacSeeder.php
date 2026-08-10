<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Syncs App\Support\PermissionCatalog into the `permissions` table and seeds
 * the system roles.
 *
 * Idempotent and safe to re-run after adding catalog keys: permissions are
 * upserted by key, and each system role's grants are re-synced to the matrix
 * below. Custom roles a super admin created are never touched.
 *
 * IMPORTANT: running this for the first time flips Role::configured() to true,
 * which switches permission checks from the legacy "any admin can do
 * everything" fallback to real granular enforcement. The `admin` role below is
 * therefore seeded with every permission EXCEPT role management, so existing
 * admins keep the access they have today.
 */
class RbacSeeder extends Seeder
{
    /**
     * System roles. Keys for the first six intentionally match the UserRole
     * enum values — that's how the legacy `users.role` column resolves to a
     * permission set (see User::effectiveRoles()).
     *
     * `level` gates privilege escalation: nobody may create, edit, or assign a
     * role at or above their own level.
     */
    private const SYSTEM_ROLES = [
        'super_admin' => [
            'name' => 'Super Admin',
            'description' => 'Unrestricted access. Bypasses every permission check and is the only role that can manage roles.',
            'level' => 100,
            'is_super' => true,
            'permissions' => '*',
        ],
        'admin' => [
            'name' => 'Admin',
            'description' => 'Full operational access to every module except role management.',
            'level' => 80,
            'is_super' => false,
            // Everything except roles.* — minting roles stays super-admin only.
            'permissions' => '*_except_roles',
        ],
        'property_manager' => [
            'name' => 'Property Manager',
            'description' => 'Manages property and resort listings, their availability, and their media.',
            'level' => 50,
            'is_super' => false,
            'permissions' => [
                'properties.view', 'properties.create', 'properties.edit',
                'properties.publish', 'properties.delete', 'properties.availability',
                'resorts.view', 'resorts.create', 'resorts.edit',
                'media.view', 'media.upload', 'media.edit', 'media.delete',
                'reports.view',
            ],
        ],
        'member_specialist' => [
            'name' => 'Member Specialist',
            'description' => 'Works member enquiries and member accounts. Read-only on listings.',
            'level' => 45,
            'is_super' => false,
            'permissions' => [
                'members.view', 'members.create', 'members.edit', 'members.assign_plan',
                'users.view',
                'properties.view', 'resorts.view', 'media.view',
                'contracts.view', 'contracts.send',
                'reports.view',
            ],
        ],
        'sales_rep' => [
            'name' => 'Sales Rep',
            'description' => 'Sells memberships: creates member accounts, assigns plans, and sends contracts.',
            'level' => 40,
            'is_super' => false,
            'permissions' => [
                'members.view', 'members.create', 'members.edit', 'members.assign_plan',
                'properties.view', 'resorts.view', 'media.view',
                'contracts.view', 'contracts.send',
                'billing.view',
                'reports.view',
            ],
        ],
        'customer_service' => [
            'name' => 'Customer Service',
            'description' => 'Supports existing customers. Can read most modules and reset passwords, but cannot publish or delete.',
            'level' => 40,
            'is_super' => false,
            'permissions' => [
                'users.view', 'users.edit', 'users.reset_password', 'users.deactivate',
                'members.view', 'members.edit',
                'properties.view', 'resorts.view', 'media.view',
                'contracts.view',
                'billing.view',
                'reports.view',
            ],
        ],
        'host' => [
            'name' => 'Host / Property Owner',
            'description' => 'Owns listings. Can edit and upload media for their own properties only.',
            'level' => 20,
            'is_super' => false,
            'permissions' => [
                'properties.view', 'properties.edit', 'properties.availability',
                'media.view', 'media.upload', 'media.edit',
            ],
        ],
        'member' => [
            'name' => 'Member',
            'description' => 'Vacation Club member. No backend access; listed so member-facing permissions have a home.',
            'level' => 10,
            'is_super' => false,
            'permissions' => [],
        ],
        'traveler' => [
            'name' => 'Traveler',
            'description' => 'Default public account. No backend access.',
            'level' => 0,
            'is_super' => false,
            'permissions' => [],
        ],
    ];

    public function run(): void
    {
        $this->syncPermissions();
        $this->syncSystemRoles();

        Role::bustCache();
    }

    /** Upsert the catalog into `permissions`, keyed on the permission key. */
    private function syncPermissions(): void
    {
        foreach (PermissionCatalog::PERMISSIONS as $key => [$module, $label, $description]) {
            Permission::query()->updateOrCreate(
                ['key' => $key],
                ['module' => $module, 'label' => $label, 'description' => $description],
            );
        }

        // Keys removed from the catalog are stale; drop them so roles can't
        // keep granting a capability the application no longer understands.
        Permission::query()->whereNotIn('key', PermissionCatalog::keys())->delete();
    }

    private function syncSystemRoles(): void
    {
        foreach (self::SYSTEM_ROLES as $key => $definition) {
            $role = Role::query()->updateOrCreate(
                ['key' => $key],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'level' => $definition['level'],
                    'is_super' => $definition['is_super'],
                    'is_system' => true,
                ],
            );

            $role->permissions()->sync($this->resolvePermissionIds($definition['permissions']));
        }
    }

    /**
     * @param  string|list<string>  $spec
     * @return list<int>
     */
    private function resolvePermissionIds(string|array $spec): array
    {
        $keys = match ($spec) {
            '*' => PermissionCatalog::keys(),
            '*_except_roles' => array_values(array_filter(
                PermissionCatalog::keys(),
                fn (string $key) => ! str_starts_with($key, 'roles.'),
            )),
            default => $spec,
        };

        return DB::table('permissions')->whereIn('key', $keys)->pluck('id')->all();
    }
}
