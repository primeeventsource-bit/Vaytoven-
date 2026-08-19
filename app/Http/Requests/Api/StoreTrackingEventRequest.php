<?php

namespace App\Http\Requests\Api;

use App\Enums\ActivityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTrackingEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Anonymous tracking is allowed (rate-limited at route level). What it
        // is allowed to SAY is constrained below.
        return true;
    }

    public function rules(): array
    {
        return [
            // An allowlist, not a length limit.
            //
            // This endpoint is public and unauthenticated by design, so a
            // request forged with curl is indistinguishable from one sent by
            // the site's own script. Accepting any string meant a visitor could
            // post account.login_succeeded, payment.approved or
            // member.contract_signed straight into the append-only activity log
            // — attributed to their own session and, if signed in, to their own
            // user id. Nothing downstream could tell those rows from the real
            // ones, and the log is meant to answer questions during disputes.
            //
            // Only browser-observable, inconsequential events are accepted. See
            // ActivityType::clientReportable(); everything in evidenceTrail()
            // is deliberately absent from it and is written by the server that
            // performed the action.
            'event_type' => [
                'required',
                'string',
                'max:64',
                Rule::in(array_merge(
                    ActivityType::clientReportable(),
                    ActivityType::legacyClientTypes(),
                )),
            ],
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

    public function messages(): array
    {
        return [
            'event_type.in' => 'That event type cannot be reported by a browser.',
        ];
    }
}
