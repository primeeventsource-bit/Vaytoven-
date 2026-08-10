<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate + authorize an edit to an existing role.
 *
 * Same escalation guards as StoreRoleRequest, plus: the `key` of any role is
 * immutable (assignments and the legacy `users.role` mapping both key on it),
 * and a system role's level is fixed.
 */
class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        $role = $this->route('role');

        if (! $actor || ! $actor->hasPermission('roles.edit') || ! $role instanceof Role) {
            return false;
        }

        if ($actor->isSuperAdmin()) {
            return true;
        }

        // Cannot edit a role at or above your own level...
        if ($role->level >= $actor->roleLevel()) {
            return false;
        }

        // ...nor promote one to or past it.
        if (! $role->is_system && (int) $this->input('level') >= $actor->roleLevel()) {
            return false;
        }

        foreach ((array) $this->input('permissions', []) as $key) {
            if (! $actor->hasPermission((string) $key)) {
                return false;
            }
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:128'],
            'description' => ['nullable', 'string', 'max:512'],
            'level' => ['required', 'integer', 'between:0,99'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::in(PermissionCatalog::keys())],
        ];
    }
}
