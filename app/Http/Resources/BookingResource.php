<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Booking
 */
class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'confirmation_code' => $this->confirmation_code,
            'property_id' => $this->property_id,
            'traveler_id' => $this->traveler_id,
            'check_in_date' => $this->check_in_date?->toDateString(),
            'check_out_date' => $this->check_out_date?->toDateString(),
            'guests' => $this->guests,
            'nights' => $this->nights,
            // Money: integer cents.
            'nightly_rate_cents' => $this->nightly_rate_cents,
            'subtotal_cents' => $this->subtotal_cents,
            'cleaning_fee_cents' => $this->cleaning_fee_cents,
            'service_fee_cents' => $this->service_fee_cents,
            'tax_cents' => $this->tax_cents,
            'total_cents' => $this->total_cents,
            'cancellation_policy' => $this->cancellation_policy?->value,
            'status' => $this->status?->value,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancelled_reason' => $this->cancelled_reason,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
