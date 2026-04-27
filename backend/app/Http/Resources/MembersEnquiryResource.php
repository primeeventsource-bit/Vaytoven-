<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MembersEnquiryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'first_name'          => $this->first_name,
            'last_name'           => $this->last_name,
            'full_name'           => $this->fullName(),
            'email'               => $this->email,
            'phone'               => $this->phone,

            'program'             => $this->program,
            'property'            => $this->property,
            'annual_points'       => $this->annual_points,
            'best_time_to_call'   => $this->best_time_to_call,
            'notes'               => $this->notes,

            'consent_given'       => $this->consent_given,
            'consent_at'          => $this->consent_at?->toIso8601String(),
            'source'              => $this->source,
            'referrer_url'        => $this->referrer_url,
            'ip_address'          => $this->ip_address,

            'status'              => $this->status,
            'assigned_to'         => $this->whenLoaded('assignee', fn () => [
                'id'    => $this->assignee->uuid,
                'name'  => $this->assignee->display_name,
                'email' => $this->assignee->email,
            ]),
            'qualified_at'        => $this->qualified_at?->toIso8601String(),
            'onboarded_at'        => $this->onboarded_at?->toIso8601String(),
            'rejection_reason'    => $this->rejection_reason,

            'converted_property'  => $this->whenLoaded('convertedProperty', fn () => [
                'id'    => $this->convertedProperty->uuid,
                'slug'  => $this->convertedProperty->slug,
                'title' => $this->convertedProperty->title,
            ]),

            'flagged'             => $this->flagged,
            'spam_score'          => $this->spam_score,

            'created_at'          => $this->created_at->toIso8601String(),
            'updated_at'          => $this->updated_at->toIso8601String(),
        ];
    }
}
