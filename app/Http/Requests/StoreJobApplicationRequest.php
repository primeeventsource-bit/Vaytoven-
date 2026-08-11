<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreJobApplicationRequest extends FormRequest
{
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
            'phone' => ['nullable', 'string', 'max:40'],
            'cover_note' => ['nullable', 'string', 'max:5000'],
            // Documents only, and capped — this endpoint is public, so the
            // mime allow-list is a security control, not a convenience.
            'resume' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'resume.mimes' => 'Please attach a PDF or Word document.',
            'resume.max' => 'Please keep the file under 5 MB.',
        ];
    }
}
