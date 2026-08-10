<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AdminAuditLogService;
use App\Support\PermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Roles & Permissions manager.
 *
 * Two invariants this controller exists to protect:
 *
 *   1. **No privilege escalation.** An actor may never create or edit a role
 *      whose level is at or above their own, nor grant a permission they do
 *      not themselves hold. Super admins bypass both (roleLevel() is
 *      PHP_INT_MAX and hasPermission() short-circuits).
 *   2. **System roles stay intact.** The seeded roles back the legacy
 *      `users.role` enum, so their `key` is immutable and they cannot be
 *      deleted. Their permission grants remain editable — that's the whole
 *      point of granular control.
 */
class RoleController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.roles.index', [
            'roles' => Role::query()
                ->withCount(['permissions', 'users'])
                ->orderByDesc('level')
                ->orderBy('name')
                ->get(),
            'actorLevel' => $request->user()->roleLevel(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.roles.create', $this->formPayload($request, new Role(['level' => 10])));
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();

        $role = DB::transaction(function () use ($data, $actor) {
            $role = Role::query()->create([
                'key' => $data['key'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'level' => (int) $data['level'],
                'is_system' => false,
                'is_super' => false,
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
            ]);

            $role->permissions()->sync($this->permissionIdsFor($data['permissions'] ?? []));

            return $role;
        });

        AdminAuditLogService::log(
            actor: $actor,
            action: 'role.create',
            subject: $role,
            payload: [
                'key' => $role->key,
                'level' => $role->level,
                'permissions' => $data['permissions'] ?? [],
            ],
            ipAddress: $request->ip(),
        );

        Role::bustCache();

        return redirect()->route('admin.roles.index')->with('success', "Role “{$role->name}” created.");
    }

    public function edit(Request $request, Role $role): View
    {
        $this->assertActorOutranks($request, $role);

        return view('admin.roles.edit', $this->formPayload($request, $role));
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $this->assertActorOutranks($request, $role);

        $data = $request->validated();
        $actor = $request->user();
        $before = [
            'name' => $role->name,
            'level' => $role->level,
            'permissions' => $role->permissionKeys(),
        ];

        DB::transaction(function () use ($role, $data, $actor) {
            $role->fill([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'updated_by_user_id' => $actor->id,
            ]);

            // System roles keep their seeded level — it's what the escalation
            // guard compares against, so operators must not be able to move it.
            if (! $role->is_system) {
                $role->level = (int) $data['level'];
            }

            $role->save();
            $role->permissions()->sync($this->permissionIdsFor($data['permissions'] ?? []));
        });

        AdminAuditLogService::log(
            actor: $actor,
            action: 'role.update',
            subject: $role,
            payload: [
                'key' => $role->key,
                'old_value' => $before,
                'new_value' => [
                    'name' => $role->name,
                    'level' => $role->level,
                    'permissions' => $data['permissions'] ?? [],
                ],
            ],
            ipAddress: $request->ip(),
        );

        Role::bustCache();

        return redirect()->route('admin.roles.index')->with('success', "Role “{$role->name}” updated.");
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $this->assertActorOutranks($request, $role);

        if ($role->is_system) {
            abort(422, 'System roles cannot be deleted.');
        }

        $assigned = $role->users()->count();
        if ($assigned > 0) {
            return redirect()->route('admin.roles.index')
                ->with('error', "“{$role->name}” is still assigned to {$assigned} user(s). Reassign them first.");
        }

        $snapshot = ['key' => $role->key, 'name' => $role->name, 'permissions' => $role->permissionKeys()];
        $role->delete();

        AdminAuditLogService::log(
            actor: $request->user(),
            action: 'role.delete',
            subject: null,
            payload: $snapshot,
            ipAddress: $request->ip(),
        );

        Role::bustCache();

        return redirect()->route('admin.roles.index')->with('success', "Role “{$snapshot['name']}” deleted.");
    }

    /**
     * An actor may only touch roles strictly below their own level. Without
     * this an Admin (level 80) could edit the Super Admin role's grants.
     */
    private function assertActorOutranks(Request $request, Role $role): void
    {
        $actor = $request->user();

        if ($actor->isSuperAdmin()) {
            return;
        }

        abort_if(
            $role->level >= $actor->roleLevel(),
            403,
            'You cannot manage a role at or above your own privilege level.',
        );
    }

    /** @return array<string, mixed> */
    private function formPayload(Request $request, Role $role): array
    {
        $actor = $request->user();

        return [
            'role' => $role,
            'catalog' => PermissionCatalog::grouped(),
            'granted' => $role->exists ? $role->permissionKeys() : [],
            // Non-super actors can only grant what they hold themselves, so the
            // editor disables the rest rather than silently dropping them.
            'grantableKeys' => $actor->isSuperAdmin()
                ? PermissionCatalog::keys()
                : array_values(array_filter(
                    PermissionCatalog::keys(),
                    fn (string $key) => $actor->hasPermission($key),
                )),
            'maxLevel' => $actor->isSuperAdmin() ? 99 : max(0, $actor->roleLevel() - 1),
        ];
    }

    /**
     * @param  list<string>  $keys
     * @return list<int>
     */
    private function permissionIdsFor(array $keys): array
    {
        $valid = array_values(array_filter($keys, PermissionCatalog::exists(...)));

        return Permission::query()->whereIn('key', $valid)->pluck('id')->all();
    }
}
