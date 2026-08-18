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
            // No longer asked for. Vaytoven advertises vacation properties;
            // collecting a club name and a points balance framed it as a
            // points-club rental programme. Still accepted so an older cached
            // copy of the form does not start failing validation mid-submit,
            // and so the columns keep working for enquiries that already have
            // them.
            'club'            => ['nullable', 'string', 'max:80'],
            'property'        => ['required', 'string', 'max:255'],
            'points'          => ['nullable', 'string', 'max:60'],
            'contact_window'  => ['nullable', 'string', 'max:120'],
            'consent'         => ['accepted'],
        ];
    }
}
