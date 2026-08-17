<?php

namespace App\Http\Requests;

use App\Enums\MemberServicePackage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMemberServiceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Note what is NOT accepted here: amount, total, price_per_week.
     *
     * The page shows a running total, but it is display only. Accepting a
     * money field from the browser — even "just to check it matches" — creates
     * the exact hole this design exists to close, because the check is one
     * refactor away from being dropped. The server computes the figure from
     * package and weeks, and there is nothing to compare it against.
     */
    public function rules(): array
    {
        $maxWeeks = max(1, (int) setting('member_services.max_weeks', 52));

        return [
            'package'    => ['required', Rule::enum(MemberServicePackage::class)],
            'weeks'      => ['required', 'integer', 'min:1', 'max:'.$maxWeeks],
            'first_name' => ['required', 'string', 'max:80'],
            'last_name'  => ['required', 'string', 'max:80'],
            'email'      => ['required', 'email:rfc', 'max:255'],
            'phone'      => ['nullable', 'string', 'max:40'],
        ];
    }

    public function messages(): array
    {
        $maxWeeks = max(1, (int) setting('member_services.max_weeks', 52));

        return [
            'package.required' => 'Choose a package to continue.',
            'weeks.max'        => "The most you can activate in one order is {$maxWeeks} weeks. Contact us for anything larger.",
            'weeks.min'        => 'Enter at least one week.',
        ];
    }
}
