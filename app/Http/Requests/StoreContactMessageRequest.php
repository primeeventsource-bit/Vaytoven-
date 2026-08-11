<?php

namespace App\Http\Requests;

use App\Enums\ContactDepartment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactMessageRequest extends FormRequest
{
    /** Public form. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:160'],
            // Optional: requiring a phone number to ask a question loses more
            // enquiries than it qualifies.
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+().\-\s]{7,40}$/'],
            'department' => ['required', Rule::enum(ContactDepartment::class)],
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'message.min' => 'Please give us a little more detail so we can help.',
            'phone.regex' => 'That phone number does not look right — digits, spaces and + only.',
        ];
    }
}
