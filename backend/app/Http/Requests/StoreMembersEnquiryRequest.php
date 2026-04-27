<?php

namespace App\Http\Requests;

use App\Models\MembersEnquiry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMembersEnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Public endpoint — anyone can submit. Throttling is enforced at the route level.
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name'        => 'required|string|min:1|max:80',
            'last_name'         => 'required|string|min:1|max:80',
            'email'             => 'required|email:rfc,dns|max:255',
            'phone'             => 'required|string|min:7|max:40',

            'program'           => ['required', 'string', Rule::in(MembersEnquiry::PROGRAMS)],
            'property'          => 'required|string|min:3|max:500',
            'annual_points'     => 'nullable|string|max:40',
            'best_time_to_call' => 'nullable|string|max:40',
            'notes'             => 'nullable|string|max:2000',

            'consent'           => 'accepted',  // checkbox; must be true

            // Provenance hints from the client; trusted only as advisory
            'source'            => ['nullable', 'string', Rule::in(MembersEnquiry::SOURCES)],
            'referrer_url'      => 'nullable|url|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'consent.accepted'  => 'Please confirm you would like to be contacted before submitting.',
            'program.in'        => 'Please pick a vacation club / program from the list.',
            'email.dns'         => 'That email address could not be verified.',
            'property.min'      => 'Please enter a property name and location (e.g. "Marriott Maui Ocean Club, Lahaina HI").',
        ];
    }

    /**
     * Attribute names for human-readable validation messages.
     */
    public function attributes(): array
    {
        return [
            'first_name'        => 'first name',
            'last_name'         => 'last name',
            'best_time_to_call' => 'best time to call',
            'annual_points'     => 'annual points balance',
        ];
    }
}
