<?php

namespace App\Http\Requests\Api;

use App\Rules\DeliverableEmailDomain;
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email', new DeliverableEmailDomain()],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
