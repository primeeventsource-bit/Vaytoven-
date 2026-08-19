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

    /**
     * The week has to belong to this property and still be open.
     *
     * Checked after the field rules rather than with an `exists` rule, because
     * "that week is on a different listing" and "that week is already under
     * offer" are different problems and a bare exists check reports neither.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $weekId = $this->input('availability_week_id');

            if (! $weekId) {
                return;
            }

            $week = \App\Models\PropertyAvailabilityWeek::find($weekId);
            $property = $this->route('property');

            if (! $week || ! $property || $week->property_id !== $property->id) {
                $validator->errors()->add('availability_week_id', 'That week is not listed on this property.');

                return;
            }

            if (! $week->status->acceptsOffers()) {
                $validator->errors()->add(
                    'availability_week_id',
                    'That week is no longer taking offers ('.$week->status->label().').'
                );
            }
        });
    }
    public function rules(): array
    {
        return [
            // Which advertised week this is for. Validated against THIS
            // property below, so a week id belonging to another listing
            // cannot be posted through this form.
            'availability_week_id' => ['nullable', 'integer'],
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
