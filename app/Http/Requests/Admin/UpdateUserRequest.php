<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validate + authorize edits to an existing user via the admin user-mgmt UI.
 *
 * Authorization rules:
 *   - Caller must be admin or super_admin.
 *   - Cannot edit super_admin accounts unless you are super_admin yourself.
 *   - Only super_admin can grant or revoke admin / super_admin role.
 *   - Cannot demote yourself if you are super_admin (we never want zero super_admins).
 */
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ?User $actor */
        $actor = $this->user();
        $target = $this->route('user');
        if (! $actor || ! $actor->isAdmin() || ! $target instanceof User) {
            return false;
        }

        // Non-super-admins can't touch super_admin accounts.
        if ($target->isSuperAdmin() && ! $actor->isSuperAdmin()) {
            return false;
        }

        $newRole = UserRole::tryFrom((string) $this->input('role'));

        // Only super_admin grants or revokes admin/super_admin.
        if ($newRole && in_array($newRole, [UserRole::Admin, UserRole::SuperAdmin], true) && ! $actor->isSuperAdmin()) {
            return false;
        }

        // Super-admins can't demote themselves (prevent zero-super-admin lockout).
        if ($actor->id === $target->id && $target->isSuperAdmin() && $newRole !== UserRole::SuperAdmin) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');

        return [
            // Last name is optional: mononyms exist, and refusing to create
            // an account over one is worse than a display name of just
            // "Prince". `name` is composed from these, never asked for.
            'first_name' => ['required', 'string', 'max:120'],
            'last_name'  => ['nullable', 'string', 'max:120'],
            'email'    => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($target->id)],
            'member_id' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'member_id')->ignore($target->id)],
            'role'     => ['required', Rule::enum(UserRole::class)],
            // Password is optional on update — only set if non-empty.
            'password' => ['nullable', 'confirmed', Password::min(10)->mixedCase()->numbers()->symbols()],
        ];
    }
}
