<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'property_slug' => 'required|string|exists:properties,slug',
            'check_in'      => 'required|date|after_or_equal:today',
            'check_out'     => 'required|date|after:check_in',
            'guests'        => 'required|integer|min:1|max:32',
            'adults'        => 'nullable|integer|min:1',
            'children'      => 'nullable|integer|min:0',
            'infants'       => 'nullable|integer|min:0',
            'pets'          => 'nullable|integer|min:0',
            'message'       => 'nullable|string|max:2000',
        ];
    }
}
