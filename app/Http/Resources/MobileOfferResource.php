<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MobileOfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $expired = $this->status->isOpen() && $this->expires_at?->isPast();

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'kind' => $this->kind?->value,
            'amount_cents' => $this->offer_amount_cents,
            'message' => $this->buyer_message,
            'status' => $expired ? 'expired' : $this->status->value,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'check_in' => $this->proposed_check_in?->toDateString(),
            'check_out' => $this->proposed_check_out?->toDateString(),
            'guests' => $this->proposed_guests,
            'owner_response' => $this->member_response_notes,
            'can_respond' => $this->property?->host_id === $request->user()->id && $this->isAwaitingOwner(),
            'is_received' => $this->property?->host_id === $request->user()->id,
            'property' => $this->property ? ['id' => $this->property->id, 'title' => $this->property->title, 'city' => $this->property->city] : null,
        ];
    }
}
