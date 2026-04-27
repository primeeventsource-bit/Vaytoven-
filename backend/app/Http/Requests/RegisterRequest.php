<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'             => 'required|email:rfc,dns|max:255|unique:users,email',
            'password'          => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()->uncompromised()],
            'first_name'        => 'required|string|min:1|max:80',
            'last_name'         => 'required|string|min:1|max:80',
            'locale'            => 'nullable|string|size:2',
            'currency'          => 'nullable|string|size:3',
            'marketing_opt_in'  => 'nullable|boolean',
            'tos_accepted'      => 'accepted',
        ];
    }

    public function messages(): array
    {
        return [
            'tos_accepted.accepted' => 'You must accept the Terms of Service to continue.',
            'password.uncompromised' => 'This password has appeared in a known data breach. Please choose another.',
        ];
    }
}
