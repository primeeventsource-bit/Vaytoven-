<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isOwner = $request->user()?->id === $this->id;

        return [
            'id'            => $this->uuid,                    // expose UUID, not internal ID
            'display_name'  => $this->display_name,
            'avatar_url'    => $this->avatar_url,
            'bio'           => $this->bio,
            'is_host'       => $this->is_host,
            'is_superhost'  => $this->is_superhost,
            'joined_year'   => $this->created_at->year,

            // Owner-only fields
            'email'              => $this->when($isOwner, $this->email),
            'phone'              => $this->when($isOwner, $this->phone),
            'locale'             => $this->when($isOwner, $this->locale),
            'currency'           => $this->when($isOwner, $this->currency),
            'two_factor_enabled' => $this->when($isOwner, $this->two_factor_enabled),
            'last_login_at'      => $this->when($isOwner, $this->last_login_at),
            'roles'              => $this->whenLoaded('roles', fn () => $this->roles->pluck('slug')),
        ];
    }
}
