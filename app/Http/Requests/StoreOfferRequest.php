<?php

namespace App\Http\Requests;

use App\Enums\OfferKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A buyer's inquiry or offer on a listing.
 *
 * `amount_dollars` is accepted from the form and converted to integer cents in
 * the controller — money is never stored or compared as a float.
 */
class StoreOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Any signed-in user may enquire; the route's auth middleware is the
        // gate. Owners submitting against their own listing is rejected in the
        // controller, where the listing is resolved.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(OfferKind::class)],
            // Required for an offer, optional for an inquiry.
            'amount_dollars' => [
                Rule::requiredIf(fn () => $this->input('kind') === OfferKind::Offer->value),
                'nullable', 'numeric', 'min:1', 'max:99999999',
            ],
            // The dates and party size the visitor is asking about. These are a
            // REQUEST, not a reservation — nothing is held and no availability
            // is blocked by submitting them.
            'check_in' => ['nullable', 'date', 'after_or_equal:today'],
            'check_out' => ['nullable', 'date', 'after:check_in'],
            'guests' => ['nullable', 'integer', 'min:1', 'max:50'],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount_dollars.required' => 'Enter the amount you would like to offer.',
            'amount_dollars.min' => 'Enter an offer of at least $1.',
            'check_out.after' => 'The check-out date must be after the check-in date.',
            'check_in.after_or_equal' => 'Choose a check-in date from today onwards.',
        ];
    }

    public function kind(): OfferKind
    {
        return OfferKind::from((string) $this->input('kind'));
    }

    /** Offer amount in integer cents, or null for an inquiry. */
    public function amountCents(): ?int
    {
        if ($this->kind() !== OfferKind::Offer) {
            return null;
        }

        return (int) round(((float) $this->input('amount_dollars')) * 100);
    }
}
