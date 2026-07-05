<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeatureFlag;
use App\Services\Settings\FeatureFlagService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Feature flag pane: toggle, scope, and rollout % per flag. Every change is
 * audited ('flag.toggle') and busts the flag cache immediately.
 */
class FeatureFlagController extends Controller
{
    public function __construct(private readonly FeatureFlagService $flags) {}

    public function index(): View
    {
        return view('admin.settings.flags', [
            'flags' => FeatureFlag::query()->with('updatedBy:id,name')->orderBy('key')->get(),
        ]);
    }

    public function update(Request $request, string $key): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'scope' => ['required', 'in:global,role,audience,environment'],
            'scope_value' => ['nullable', 'string', 'max:64', 'required_unless:scope,global'],
            'rollout_pct' => ['nullable', 'integer', 'between:0,100'],
        ]);

        if ($data['scope'] === 'global') {
            $data['scope_value'] = null;
        }

        $this->flags->update($key, $data, $request->user(), $request->ip());

        return redirect()->route('admin.settings.flags')
            ->with('success', "Flag '{$key}' updated.");
    }
}
