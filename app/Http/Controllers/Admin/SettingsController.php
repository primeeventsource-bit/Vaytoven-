<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Settings\SettingsRepository;
use App\Services\Settings\SettingsSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Admin panes for the Layer-1 settings groups. Panes render entirely from
 * SettingsSchema metadata, so adding a key server-side surfaces it in the
 * UI automatically. Sensitive groups (payments/security/integrations/legal)
 * are super-admin only — enforced here, not just hidden in the nav.
 */
class SettingsController extends Controller
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function index(): RedirectResponse
    {
        return redirect()->route('admin.settings.group', 'general');
    }

    public function showGroup(Request $request, string $group): View
    {
        abort_unless(array_key_exists($group, SettingsSchema::GROUPS), 404);
        $this->authorizeGroup($request, $group);

        return view('admin.settings.group', [
            'group' => $group,
            'groupLabel' => SettingsSchema::GROUPS[$group],
            'fields' => $this->settings->groupForDisplay($group),
            'lastChange' => $this->lastChangeFor($group),
        ]);
    }

    public function updateGroup(Request $request, string $group): RedirectResponse
    {
        abort_unless(array_key_exists($group, SettingsSchema::GROUPS), 404);
        $this->authorizeGroup($request, $group);

        $input = (array) $request->input('settings', []);
        $specs = SettingsSchema::group($group);

        // Validate every submitted key first; only write when all pass.
        $errors = [];
        $updates = [];
        foreach ($specs as $key => $spec) {
            if (! array_key_exists($key, $input)) {
                continue; // not on the submitted form
            }

            $value = $input[$key];

            // Sensitive fields: blank means "keep current value".
            if ($spec['sensitive'] && ($value === null || $value === '')) {
                continue;
            }

            // Cents are edited as dollars in the UI; convert before validation.
            if ($spec['type'] === 'cents' && is_numeric($value)) {
                $value = (int) round(((float) $value) * 100);
            }

            try {
                $updates[$key] = $this->settings->validateValue($key, $value);
            } catch (ValidationException $e) {
                $errors[$key] = collect($e->errors())->flatten()->first();
            }
        }

        if ($errors !== []) {
            return back()->withErrors($errors)->withInput();
        }

        DB::transaction(function () use ($updates, $request) {
            foreach ($updates as $key => $value) {
                $this->settings->set($key, $value, $request->user(), $request->ip());
            }
        });

        return redirect()->route('admin.settings.group', $group)
            ->with('success', count($updates).' setting(s) saved.');
    }

    private function authorizeGroup(Request $request, string $group): void
    {
        if (in_array($group, SettingsSchema::SENSITIVE_GROUPS, true)) {
            abort_unless($request->user()->isSuperAdmin(), 403, 'This settings group requires a super admin.');
        }
    }

    /** "Last changed by {actor} · {time}" line for the pane footer. */
    private function lastChangeFor(string $group): ?Setting
    {
        return Setting::query()
            ->where('group', $group)
            ->whereNotNull('updated_by')
            ->with('updatedBy:id,name,email')
            ->orderByDesc('updated_at')
            ->first();
    }
}
