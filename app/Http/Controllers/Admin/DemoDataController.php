<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\DemoDataPurge;
use App\Services\AdminAuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Removing the seeded demo accounts and their data.
 *
 * Super admin only, and not by permission alone. Every other destructive action
 * in the admin is scoped to one row that somebody navigated to; this one takes
 * out a set in a single press, so it is restricted to the role that already
 * bypasses every permission check rather than to a permission that could be
 * granted to somebody it was not meant for.
 *
 * Nothing is destroyed without a preview and a typed confirmation. "Delete the
 * demo data" is a sentence that means different things to different people, so
 * the screen shows exactly which accounts and how many rows first.
 */
class DemoDataController extends Controller
{
    /** Typed by hand before anything is removed. */
    private const CONFIRMATION = 'DELETE DEMO DATA';

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

        return view('admin.demo-data.index', [
            'preview'      => (new DemoDataPurge())->preview(),
            'confirmation' => self::CONFIRMATION,
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);

        $request->validate([
            'confirmation' => ['required', 'string'],
        ]);

        if ($request->string('confirmation')->toString() !== self::CONFIRMATION) {
            return back()->withErrors([
                'confirmation' => 'Type '.self::CONFIRMATION.' exactly to confirm. Nothing was removed.',
            ]);
        }

        $purge   = new DemoDataPurge();
        $preview = $purge->preview();

        // Audited before the rows go, so the record of what was removed
        // outlives the thing it describes. The account list is kept in full:
        // "18 accounts" is not an answer to "which ones".
        AdminAuditLogService::log(
            actor:     $request->user(),
            action:    'demo_data.purged',
            subject:   $request->user(),
            payload:   [
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
                ? 'Nothing matched — there is no demo data left to remove.'
                : 'Removed '.$summary.'.');
    }
}
