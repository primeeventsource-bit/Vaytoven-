<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Where browsers post Content-Security-Policy violations.
 *
 * The policy ships report-only, which is worth nothing unless somebody reads
 * the reports. This writes them to the application log so the policy can be
 * tightened into enforcement on evidence rather than on hope.
 *
 * The endpoint is public and unauthenticated because it has to be — the
 * browser posts it, not the user. So it is treated as hostile input: only the
 * handful of fields that matter are kept, each is truncated, and the route is
 * rate limited. An open endpoint that appends attacker-controlled text to a
 * log is a log-flooding tool otherwise.
 */
class CspReportController extends Controller
{
    private const KEEP = [
        'document-uri', 'referrer', 'violated-directive', 'effective-directive',
        'blocked-uri', 'source-file', 'line-number', 'status-code', 'disposition',
    ];

    private const MAX_LENGTH = 500;

    public function __invoke(Request $request): Response
    {
        $report = $request->input('csp-report');

        if (! is_array($report)) {
            // Reporting API v1 posts a different shape. Not an error worth
            // making noise about — just nothing to record.
            return response()->noContent();
        }

        $kept = [];

        foreach (self::KEEP as $field) {
            if (! array_key_exists($field, $report)) {
                continue;
            }

            $value = $report[$field];
            $kept[$field] = is_scalar($value)
                ? mb_substr((string) $value, 0, self::MAX_LENGTH)
                : null;
        }

        Log::warning('csp: policy violation reported.', $kept + ['ip' => $request->ip()]);

        return response()->noContent();
    }
}
