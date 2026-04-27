<?php

namespace App\Http\Requests;

use App\Models\MembersEnquiry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMembersEnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'status'            => ['nullable', 'string', Rule::in(MembersEnquiry::STATUSES)],
            'assigned_to'       => 'nullable|integer|exists:users,id',
            'rejection_reason'  => 'nullable|string|max:2000|required_if:status,rejected',
            'flagged'           => 'nullable|boolean',
            'notes'             => 'nullable|string|max:5000',
        ];
    }

    public function messages(): array
    {
        return [
            'rejection_reason.required_if' => 'A rejection reason is required when marking an enquiry rejected.',
        ];
    }
}
