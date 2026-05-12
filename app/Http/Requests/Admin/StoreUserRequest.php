<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validate + authorize a new user being created via the admin user-mgmt UI.
 *
 * Authorization rules:
 *   - Caller must be admin or super_admin (route is already gated).
 *   - Only super_admin can create users with role=admin or role=super_admin.
 *     Regular admins can create everything below that line.
 */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        if (! $actor || ! $actor->isAdmin()) {
            return false;
        }

        $newRole = UserRole::tryFrom((string) $this->input('role'));
        // Only super_admin can grant admin or super_admin.
        if (in_array($newRole, [UserRole::Admin, UserRole::SuperAdmin], true) && ! $actor->isSuperAdmin()) {
            return false;
        }
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()->symbols()],
            'role'     => ['required', Rule::enum(UserRole::class)],
        ];
    }
}
