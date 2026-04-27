<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isHost = $user && $user->id === $this->host_id;

        return [
            'id'                  => $this->uuid,
            'confirmation_code'   => $this->confirmation_code,
            'check_in'            => $this->check_in?->toDateString(),
            'check_out'           => $this->check_out?->toDateString(),
            'nights'              => $this->nights,
            'guests'              => $this->guests,

            'pricing' => [
                'base_price_cents'        => $this->base_price_cents,
                'cleaning_fee_cents'      => $this->cleaning_fee_cents,
                'extra_guest_fee_cents'   => $this->extra_guest_fee_cents,
                'subtotal_cents'          => $this->subtotal_cents,
                'guest_service_fee_cents' => $this->guest_service_fee_cents,
                'tax_cents'               => $this->tax_cents,
                'total_cents'             => $this->total_cents,
                'currency'                => $this->currency,
                // Host-only fields
                'host_service_fee_cents'  => $this->when($isHost, $this->host_service_fee_cents),
                'host_payout_cents'       => $this->when($isHost, $this->host_payout_cents),
            ],

            'status'         => $this->status,
            'payment_status' => $this->payment_status,

            'cancellation_policy' => $this->cancellation_policy,
            'guest_message'       => $this->guest_message,

            'property' => new PropertyResource($this->whenLoaded('property')),
            'guest'    => new UserResource($this->whenLoaded('guest')),
            'host'     => new UserResource($this->whenLoaded('host')),

            'confirmed_at' => $this->confirmed_at,
            'cancelled_at' => $this->cancelled_at,
            'created_at'   => $this->created_at,
        ];
    }
}
