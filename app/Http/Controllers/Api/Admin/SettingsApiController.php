<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeatureFlag;
use App\Services\Settings\FeatureFlagService;
use App\Services\Settings\SettingsRepository;
use App\Services\Settings\SettingsSchema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * JSON admin surface under /api/v1/admin (Sanctum + admin middleware; the
 * sensitive groups additionally require super admin). Mirrors the web
 * console: reads are schema-merged and redacted, writes are validated
 * against the allow-list, transactional, audited, and cache-busting.
 */
class SettingsApiController extends Controller
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly FeatureFlagService $flags,
    ) {}

    /** GET /admin/settings — grouped map of all settings (redacted). */
    public function index(Request $request): JsonResponse
    {
        $groups = [];
        foreach (SettingsSchema::GROUPS as $group => $label) {
            if ($this->cannotViewGroup($request, $group)) {
                continue;
            }
            $groups[$group] = array_values($this->settings->groupForDisplay($group));
        }

        return response()->json(['groups' => $groups]);
    }

    /** GET /admin/settings/{group} */
    public function showGroup(Request $request, string $group): JsonResponse
    {
        abort_unless(array_key_exists($group, SettingsSchema::GROUPS), 404);
        $this->authorizeGroup($request, $group);

        return response()->json([
            'group' => $group,
            'settings' => array_values($this->settings->groupForDisplay($group)),
        ]);
    }

    /** PUT /admin/settings/{group} — bulk, transactional, per-key validated. */
    public function updateGroup(Request $request, string $group): JsonResponse
    {
        abort_unless(array_key_exists($group, SettingsSchema::GROUPS), 404);
        $this->authorizeGroup($request, $group);

        $input = (array) $request->input('settings', []);
        $specs = SettingsSchema::group($group);

        $errors = [];
        $updates = [];
        foreach ($input as $key => $value) {
            $spec = $specs[$key] ?? null;
            if ($spec === null) {
                $errors[$key] = ["Unknown setting key '{$key}' for group '{$group}'."];

                continue;
            }
            if ($spec['sensitive'] && ($value === null || $value === '')) {
                continue; // blank sensitive value = keep current
            }

            try {
                $updates[$key] = $this->settings->validateValue($key, $value);
            } catch (ValidationException $e) {
                $errors[$key] = collect($e->errors())->flatten()->all();
            }
        }

        if ($errors !== []) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $errors], 422);
        }

        DB::transaction(function () use ($updates, $request) {
            foreach ($updates as $key => $value) {
                $this->settings->set($key, $value, $request->user(), $request->ip());
            }
        });

        return $this->showGroup($request, $group);
    }

    /** GET /admin/feature-flags */
    public function flags(): JsonResponse
    {
        return response()->json(['flags' => FeatureFlag::query()->orderBy('key')->get()]);
    }

    /** PUT /admin/feature-flags/{key} */
    public function updateFlag(Request $request, string $key): JsonResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'scope' => ['sometimes', 'in:global,role,audience,environment'],
            'scope_value' => ['nullable', 'string', 'max:64'],
            'rollout_pct' => ['nullable', 'integer', 'between:0,100'],
        ]);

        $flag = $this->flags->update($key, $data, $request->user(), $request->ip());

        return response()->json(['flag' => $flag->fresh()]);
    }

    private function authorizeGroup(Request $request, string $group): void
    {
        if ($this->cannotViewGroup($request, $group)) {
            abort(403, 'This settings group requires a super admin.');
        }
    }

    private function cannotViewGroup(Request $request, string $group): bool
    {
        return in_array($group, SettingsSchema::SENSITIVE_GROUPS, true)
            && ! $request->user()->isSuperAdmin();
    }
}
