<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMemberEnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Public endpoint — anyone can submit an enquiry. No auth gating.
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name'      => ['required', 'string', 'max:80'],
            'last_name'       => ['required', 'string', 'max:80'],
            'email'           => ['required', 'email', 'max:160'],
            'phone'           => ['required', 'string', 'max:40'],
            'club'            => ['required', 'string', 'max:80'],
            'property'        => ['required', 'string', 'max:255'],
            'points'          => ['required', 'string', 'max:60'],
            'contact_window'  => ['nullable', 'string', 'max:120'],
            'consent'         => ['accepted'],
        ];
    }
}
