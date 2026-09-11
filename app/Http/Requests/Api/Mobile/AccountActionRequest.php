<?php

namespace App\Http\Requests\Api\Mobile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class AccountActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return match ($this->route()->getName()) {
            'mobile.terms.store' => ['accept' => ['required', 'accepted'], 'version_ids' => ['required', 'array'], 'version_ids.*' => ['integer', 'distinct']],
            'mobile.password.update' => ['current_password' => ['required', 'string'], 'password' => ['required', 'confirmed', 'different:current_password', Password::min(10)->letters()->numbers()]],
            'mobile.offers.respond' => ['decision' => ['required', 'in:accepted,declined'], 'notes' => ['nullable', 'string', 'max:2000']],
            default => [],
        };
    }
}
