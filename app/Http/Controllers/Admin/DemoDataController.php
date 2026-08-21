<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\DemoDataPurge;
use App\Services\AdminAuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Removing the throwaway accounts and their data.
 *
 * Super admin only, and not by permission alone. Every other destructive action
 * in the admin is scoped to one row that somebody navigated to; this one takes
 * out a set in a single press, so it is restricted to the role that already
 * bypasses every permission check rather than to a permission that could be
 * granted to somebody it was not meant for.
 *
 * There are two groups and they are removed separately. The test accounts are
 * exhaust from the end-to-end suite and can go at any time; the demo accounts
 * host the listings that keep the public site from looking empty and stay until
 * the real listings can carry it. Each has its own confirmation phrase, so a
 * press meant for one cannot reach the other — typing DELETE TEST DATA into the
 * demo form removes nothing.
 *
 * Nothing is destroyed without a preview and a typed confirmation. "Delete the
 * demo data" is a sentence that means different things to different people, so
 * the screen shows exactly which accounts and how many rows first.
 */
class DemoDataController extends Controller
{
    private function authorizeSuperAdmin(Request $request): void
    {
        abort_unless(
            $request->user()?->isSuperAdmin(),
            403,
            'Only a super admin can remove demo data.',
        );
    }

    public function index(Request $request): View
    {
        $this->authorizeSuperAdmin($request);

        $groups = [];

        foreach (DemoDataPurge::GROUPS as $key => $group) {
            $groups[$key] = $group + [
                'key'     => $key,
                'preview' => DemoDataPurge::forGroup($key)->preview(),
            ];
        }

        return view('admin.demo-data.index', [
            'groups'   => $groups,
            'progress' => DemoDataPurge::realListingProgress(),
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);

        $request->validate([
            'scope'        => ['required', 'string', 'in:'.implode(',', array_keys(DemoDataPurge::GROUPS))],
            'confirmation' => ['required', 'string'],
        ]);

        $key   = $request->string('scope')->toString();
        $group = DemoDataPurge::GROUPS[$key];

        // Checked against this group's phrase alone. The other group's phrase is
        // wrong here, which is the point: one form cannot be used to run the other.
        if ($request->string('confirmation')->toString() !== $group['confirm']) {
            return back()->withErrors([
                'confirmation' => 'Type '.$group['confirm'].' exactly to remove the '
                    .lcfirst($group['label']).'. Nothing was removed.',
            ]);
        }

        $purge   = DemoDataPurge::forGroup($key);
        $preview = $purge->preview();

        // Audited before the rows go, so the record of what was removed
        // outlives the thing it describes. The account list is kept in full:
        // "18 accounts" is not an answer to "which ones".
        AdminAuditLogService::log(
            actor:     $request->user(),
            action:    'demo_data.purged',
            subject:   $request->user(),
            payload:   [
                'scope'    => $key,
                'suffix'   => $preview['suffix'],
                'accounts' => array_column($preview['accounts'], 'email'),
                'counts'   => $preview['counts'],
            ],
            ipAddress: $request->ip(),
        );

        $removed = $purge->purge($request->user());

        $summary = collect($removed)
            ->filter(fn (int $n) => $n > 0)
            ->map(fn (int $n, string $k) => "{$n} {$k}")
            ->implode(', ');

        return redirect()
            ->route('admin.demo-data.index')
            ->with('success', $summary === ''
                ? 'Nothing matched — there is no '.lcfirst($group['label']).' left to remove.'
                : 'Removed '.$summary.'.');
    }
}
