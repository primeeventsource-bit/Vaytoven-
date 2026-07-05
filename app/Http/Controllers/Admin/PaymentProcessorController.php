<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentProcessorConfig;
use App\Services\AdminAuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

/**
 * Payment processor manager (any admin, via the `admin` middleware).
 * Credentials are write-only: they are encrypted at rest, redacted
 * everywhere they are displayed, and an empty submitted field means "keep
 * the current value" so a save can never accidentally blank a key.
 */
class PaymentProcessorController extends Controller
{
    /** Credential fields shown per processor code (all masked in the UI). */
    public const CREDENTIAL_FIELDS = [
        'nmi' => ['security_key', 'tokenization_key', 'webhook_signing_key'],
        'default' => ['api_key', 'api_secret'],
    ];

    public function index(Request $request): View
    {
        return view('admin.settings.processors', [
            'processors' => PaymentProcessorConfig::query()->with('updatedBy:id,name')->orderBy('priority')->get(),
            'credentialFields' => self::CREDENTIAL_FIELDS,
        ]);
    }

    public function update(Request $request, string $code): RedirectResponse
    {
        $processor = PaymentProcessorConfig::query()->where('code', $code)->firstOrFail();

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'mode' => ['required', 'in:test,live'],
            'priority' => ['required', 'integer', 'between:1,999'],
            'is_default' => ['sometimes', 'boolean'],
            'min_amount_dollars' => ['nullable', 'numeric', 'min:0'],
            'max_amount_dollars' => ['nullable', 'numeric', 'min:0'],
            'credentials' => ['sometimes', 'array'],
            'credentials.*' => ['nullable', 'string', 'max:512'],
        ]);

        $before = [
            'enabled' => $processor->enabled,
            'mode' => $processor->mode,
            'priority' => $processor->priority,
            'is_default' => $processor->is_default,
        ];

        // Empty credential input = keep current (never blank a key by accident).
        $credentials = $processor->credentials ?? [];
        $credentialsChanged = [];
        foreach ((array) ($data['credentials'] ?? []) as $field => $value) {
            if ($value !== null && $value !== '') {
                $credentials[$field] = $value;
                $credentialsChanged[] = $field;
            }
        }

        DB::transaction(function () use ($processor, $data, $credentials, $request) {
            $processor->fill([
                'enabled' => (bool) $data['enabled'],
                'mode' => $data['mode'],
                'priority' => (int) $data['priority'],
                'min_amount_cents' => isset($data['min_amount_dollars']) && $data['min_amount_dollars'] !== null
                    ? (int) round(((float) $data['min_amount_dollars']) * 100) : null,
                'max_amount_cents' => isset($data['max_amount_dollars']) && $data['max_amount_dollars'] !== null
                    ? (int) round(((float) $data['max_amount_dollars']) * 100) : null,
            ]);
            $processor->credentials = $credentials;
            $processor->updated_by = $request->user()->id;

            if ($request->boolean('is_default')) {
                PaymentProcessorConfig::query()
                    ->whereKeyNot($processor->id)
                    ->update(['is_default' => false]);
                $processor->is_default = true;
            }

            $processor->save();
        });

        AdminAuditLogService::log(
            actor: $request->user(),
            action: 'processor.update',
            subject: $processor,
            payload: [
                'code' => $code,
                'old_value' => $before,
                'new_value' => $processor->only(['enabled', 'mode', 'priority', 'is_default']),
                // Only WHICH fields changed — never the secrets themselves.
                'credentials_changed' => $credentialsChanged === [] ? null : array_fill_keys($credentialsChanged, PaymentProcessorConfig::REDACTED),
            ],
            ipAddress: $request->ip(),
        );

        PaymentProcessorConfig::bustCache();

        return redirect()->route('admin.settings.processors')
            ->with('success', "{$processor->display_name} updated.");
    }

    /**
     * Server-side connectivity check. Returns pass/fail only — no response
     * bodies or secrets ever reach the browser.
     */
    public function test(Request $request, string $code): RedirectResponse
    {
        $processor = PaymentProcessorConfig::query()->where('code', $code)->firstOrFail();

        [$ok, $message] = $this->runConnectivityCheck($processor);

        AdminAuditLogService::log(
            actor: $request->user(),
            action: 'processor.test',
            subject: $processor,
            payload: ['code' => $code, 'result' => $ok ? 'pass' : 'fail'],
            ipAddress: $request->ip(),
        );

        return redirect()->route('admin.settings.processors')
            ->with($ok ? 'success' : 'error', "{$processor->display_name}: {$message}");
    }

    /** @return array{0: bool, 1: string} */
    private function runConnectivityCheck(PaymentProcessorConfig $processor): array
    {
        if ($processor->code !== 'nmi') {
            return [false, 'No connectivity check implemented for this processor yet.'];
        }

        $securityKey = $processor->credentials['security_key']
            ?? config('services.nmi.security_key');

        if (! $securityKey) {
            return [false, 'Connection test failed — no security key configured.'];
        }

        try {
            // A keyed request with no transaction type: NMI answers with its
            // standard response codes. "Authentication Failed" = bad key;
            // any other parsed response = endpoint reachable, key accepted.
            $response = Http::asForm()->timeout(10)->post(
                config('services.nmi.endpoint', 'https://secure.nmi.com/api/transact.php'),
                ['security_key' => $securityKey],
            );

            parse_str($response->body(), $parsed);
            $text = strtolower((string) ($parsed['responsetext'] ?? ''));

            if (str_contains($text, 'authentication failed') || str_contains($text, 'invalid security key')) {
                return [false, 'Connection test failed — NMI rejected the security key.'];
            }

            return [true, 'Connection test passed — NMI endpoint reachable and key accepted.'];
        } catch (\Throwable) {
            return [false, 'Connection test failed — NMI endpoint unreachable.'];
        }
    }
}
