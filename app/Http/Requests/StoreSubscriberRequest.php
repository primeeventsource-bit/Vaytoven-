<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubscriberRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Public endpoint — anyone can subscribe to the newsletter.
        return true;
    }

    public function rules(): array
    {
        return [
            // Lengths mirror the column constraints.
            'full_name' => ['required', 'string', 'min:2', 'max:160'],
            'email'     => ['required', 'email', 'max:191'],
            'phone'     => ['nullable', 'string', 'max:40'],
        ];
    }
}
