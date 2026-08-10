<?php

namespace App\Http\Requests\Admin;

use App\Support\PermissionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate + authorize a new custom role.
 *
 * Authorization mirrors RoleController's invariants at the request layer so
 * they hold for any future caller (API, console) that reuses this request:
 * the actor needs `roles.create`, may not mint a role at or above their own
 * privilege level, and may not grant a permission they do not hold.
 */
class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        if (! $actor || ! $actor->hasPermission('roles.create')) {
            return false;
        }

        if ($actor->isSuperAdmin()) {
            return true;
        }

        if ((int) $this->input('level') >= $actor->roleLevel()) {
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
            // Slug is permanent once set — it's what role assignments key on.
            'key' => [
                'required', 'string', 'max:64',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('roles', 'key'),
            ],
            'name' => ['required', 'string', 'max:128'],
            'description' => ['nullable', 'string', 'max:512'],
            'level' => ['required', 'integer', 'between:0,99'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::in(PermissionCatalog::keys())],
        ];
    }

    public function messages(): array
    {
        return [
            'key.regex' => 'The role key must be lowercase letters, numbers, and underscores, starting with a letter.',
        ];
    }
}
