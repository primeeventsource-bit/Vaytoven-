<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SearchPropertiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:128'],
            'city' => ['nullable', 'string', 'max:128'],
            'country' => ['nullable', 'string', 'size:2'],
            'min_capacity' => ['nullable', 'integer', 'min:1', 'max:32'],
            'max_price_cents' => ['nullable', 'integer', 'min:0'],
            // Bounding-box geo prefilter (FR-2.4).
            'lat_min' => ['nullable', 'numeric', 'between:-90,90'],
            'lat_max' => ['nullable', 'numeric', 'between:-90,90'],
            'lng_min' => ['nullable', 'numeric', 'between:-180,180'],
            'lng_max' => ['nullable', 'numeric', 'between:-180,180'],
            // Pagination.
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
