<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminAuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'action'       => $this->action,
            'admin'        => $this->whenLoaded('admin', fn () => [
                'id'           => $this->admin->uuid,
                'display_name' => $this->admin->display_name,
                'email'        => $this->admin->email,
            ]),
            'target' => $this->target_type ? [
                'type' => $this->target_type,
                'id'   => $this->target_uuid ?? $this->target_id,
            ] : null,
            'before_state' => $this->before_state,
            'after_state'  => $this->after_state,
            'diff'         => $this->diff(),
            'reason'       => $this->reason,
            'ip_address'   => $this->ip_address,
            'user_agent'   => $this->user_agent,
            'created_at'   => $this->created_at?->toIso8601String(),
        ];
    }
}
