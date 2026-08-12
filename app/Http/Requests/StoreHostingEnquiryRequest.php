<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHostingEnquiryRequest extends FormRequest
{
    /** Public — an owner should not need an account to ask about listing. */
    public function authorize(): bool
    {
        return true;
    }

    /** Pre-fill from the signed-in account so a member doesn't retype it. */
    protected function prepareForValidation(): void
    {
        if ($user = $this->user()) {
            $this->merge([
                'first_name' => $this->input('first_name') ?: $user->first_name,
                'last_name' => $this->input('last_name') ?: $user->last_name,
                'email' => $this->input('email') ?: $user->email,
                'phone' => $this->input('phone') ?: $user->phone,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+().\-\s]{7,40}$/'],

            'listing_kind' => ['nullable', 'in:property,resort'],

            // Everything about the property or resort is optional. An owner who
            // only knows "I have a place in Tahoe" should still be able to start
            // the conversation; the team fills the rest in on the call.
            'property_name' => ['nullable', 'string', 'max:200'],
            'resort_name' => ['nullable', 'string', 'max:200'],
            'club_or_developer' => ['nullable', 'string', 'max:160'],
            'ownership_details' => ['nullable', 'string', 'max:200'],
            'property_type' => ['nullable', 'string', 'max:64'],
            'city' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'bedrooms' => ['nullable', 'integer', 'min:0', 'max:200'],
            'bathrooms' => ['nullable', 'integer', 'min:0', 'max:200'],
            'indicative_nightly_dollars' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'availability' => ['nullable', 'string', 'max:200'],
            'message' => ['nullable', 'string', 'max:4000'],
        ];
    }

    /** Integer cents, or null. Money is never stored as a float. */
    public function indicativeNightlyCents(): ?int
    {
        $value = $this->validated('indicative_nightly_dollars');

        return $value === null || $value === '' ? null : (int) round(((float) $value) * 100);
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'That phone number does not look right — digits, spaces and + only.',
        ];
    }
}
