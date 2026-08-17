<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminAuditLogService;
use App\Services\Members\MemberProfileAssembler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The Member 360 screen — one page holding everything about one member.
 *
 * The tabs are rendered server-side and switched with a query parameter, not
 * JavaScript state. That keeps every tab linkable and back-button-correct, and
 * means a member's payments tab can be pasted into a message to a colleague.
 */
class MemberProfileController extends Controller
{
    private const TABS = [
        'overview'   => 'Overview',
        'properties' => 'Properties',
        'analytics'  => 'Analytics',
        'offers'     => 'Offers',
        'documents'  => 'Contracts & documents',
        'payments'   => 'Payments',
        'activity'   => 'Activity log',
    ];

    public function __construct(private readonly MemberProfileAssembler $assembler)
    {
    }

    public function show(Request $request, User $user): View
    {
        $tab = $request->query('tab', 'overview');

        if (! array_key_exists($tab, self::TABS)) {
            $tab = 'overview';
        }

        return view('admin.members.show', $this->assembler->assemble($user) + [
            'tabs'      => self::TABS,
            'activeTab' => $tab,
        ]);
    }

    /** Staff notes. Audited, because they are staff-authored account content. */
    public function updateNotes(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'staff_notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $user->update(['staff_notes' => $validated['staff_notes'] ?? null]);

        AdminAuditLogService::log(
            actor:     $request->user(),
            action:    'user.notes_updated',
            subject:   $user,
            // The note body is not copied into the audit payload — it would
            // duplicate content that is already on the record and is often
            // the sort of thing that should not be repeated in a log.
            payload:   ['email' => $user->email, 'length' => strlen($validated['staff_notes'] ?? '')],
            ipAddress: $request->ip(),
        );

        return back()->with('success', 'Notes saved.');
    }
}
