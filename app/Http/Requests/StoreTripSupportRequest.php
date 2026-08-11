<?php

namespace App\Http\Requests;

use App\Enums\SupportCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTripSupportRequest extends FormRequest
{
    /** Public — someone locked out of their account still needs support. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Pre-fill from the signed-in account so a logged-in traveller does not
     * retype what we already know.
     */
    protected function prepareForValidation(): void
    {
        if ($user = $this->user()) {
            $this->merge([
                'name' => $this->input('name') ?: $user->name,
                'email' => $this->input('email') ?: $user->email,
                'phone' => $this->input('phone') ?: $user->phone,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email:rfc', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+().\-\s]{7,40}$/'],
            'category' => ['required', Rule::enum(SupportCategory::class)],
            // Free text: a guest may have a confirmation code, a listing name,
            // or just a URL. Any of those is enough for an agent to find it.
            'property_reference' => ['nullable', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'message.min' => 'Tell us a bit more about what went wrong so we can help faster.',
        ];
    }
}
