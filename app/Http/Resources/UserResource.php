<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'must_change_password' => (bool) $this->must_change_password,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role?->value,
            'email_verified' => ! is_null($this->email_verified_at),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
