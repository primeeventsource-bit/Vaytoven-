<?php

namespace App\Http\Requests;

use App\Models\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Tightened by the controller's middleware/policy; FormRequest just
        // shapes input. Once auth lands, gate this on $this->user()->is_admin.
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'        => ['nullable', 'integer'],
            'client_name'    => ['required', 'string', 'max:160'],
            'client_email'   => ['required', 'email', 'max:200'],
            'client_phone'   => ['nullable', 'string', 'max:40'],
            'contract_type'  => ['required', Rule::in([
                Contract::TYPE_HOST_LISTING,
                Contract::TYPE_MEMBER_PROGRAM,
                Contract::TYPE_BOOKING_TERMS,
                Contract::TYPE_CUSTOM,
            ])],
            'title'          => ['required', 'string', 'max:200'],
            'template_id'    => ['nullable', 'string', 'max:80'],
            'pdf'            => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'payment_id'     => ['nullable', 'string', 'max:120'],
            'source'         => ['nullable', Rule::in([
                Contract::SOURCE_WEB, Contract::SOURCE_APP, Contract::SOURCE_ADMIN,
            ])],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if (! $this->filled('template_id') && ! $this->hasFile('pdf')) {
                $v->errors()->add('template_id', 'Provide either a DocuSign template_id or upload a PDF document.');
            }
        });
    }
}
