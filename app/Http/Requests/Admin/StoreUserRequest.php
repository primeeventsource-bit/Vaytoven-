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
            // Last name is optional: mononyms exist, and refusing to create
            // an account over one is worse than a display name of just
            // "Prince". `name` is composed from these, never asked for.
            'first_name' => ['required', 'string', 'max:120'],
            'last_name'  => ['nullable', 'string', 'max:120'],
            'email'    => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            // Typed by staff, so it must be unique or two members end up
            // sharing listing addresses. Blank is allowed: not everyone
            // with an account is a member.
            'member_id' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'member_id')],
            'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()->symbols()],
            'role'     => ['required', Rule::enum(UserRole::class)],
        ];
    }
}
