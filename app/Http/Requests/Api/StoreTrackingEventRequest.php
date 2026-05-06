<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreTrackingEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Anonymous tracking is allowed (rate-limited at route level).
        return true;
    }

    public function rules(): array
    {
        return [
            'event_type' => ['required', 'string', 'max:64'],
            'visitor_id' => ['nullable', 'string', 'max:36'],
            'metadata' => ['nullable', 'array'],
            // First-touch UTM capture (optional).
            'utm_source' => ['nullable', 'string', 'max:64'],
            'utm_medium' => ['nullable', 'string', 'max:64'],
            'utm_campaign' => ['nullable', 'string', 'max:128'],
            'utm_term' => ['nullable', 'string', 'max:128'],
            'utm_content' => ['nullable', 'string', 'max:128'],
            'gclid' => ['nullable', 'string', 'max:128'],
            'fbclid' => ['nullable', 'string', 'max:128'],
        ];
    }
}
