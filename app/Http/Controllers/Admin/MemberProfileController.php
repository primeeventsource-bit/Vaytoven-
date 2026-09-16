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
        'overview'    => 'Overview',
        'properties'  => 'Properties',
        'advertising' => 'Advertising',
        'analytics'   => 'Analytics',
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

    /**
     * The Advertisement Service Fulfillment Record for one of this member's
     * advertisements, generated from stored audit records. Every download is
     * written to the admin audit log with the certificate number it carried.
     */
    public function fulfillmentCertificate(
        Request $request,
        User $user,
        \App\Models\Property $property,
        \App\Services\Fulfillment\FulfillmentCertificate $certificate,
    ): \Illuminate\Http\Response {
        abort_unless((int) $property->host_id === (int) $user->id, 404);

        $payload = $certificate->payload($property);

        AdminAuditLogService::log(
            actor:     $request->user(),
            action:    'member.fulfillment_certificate.downloaded',
            subject:   $user,
            payload:   [
                'certificate' => $payload['certificateNumber'],
                'property'    => $property->reference,
                'status'      => $payload['status'],
            ],
            ipAddress: $request->ip(),
        );

        return response($certificate->render($property), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$certificate->filename($property).'"',
        ]);
    }

    /** Choose which enrollment offer this client will be sent. Locked once presented. */
    public function assignIncentive(
        Request $request,
        User $user,
        \App\Services\Fulfillment\MemberIncentive $incentive,
    ): RedirectResponse {
        $validated = $request->validate([
            'incentive_key' => ['required', 'in:'.implode(',', \App\Services\Fulfillment\IncentiveCatalog::keys())],
        ]);

        try {
            $incentive->assign($user, $validated['incentive_key'], $request->user(), $request);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['incentive_key' => $e->getMessage()]);
        }

        return redirect()->route('admin.members.show', ['user' => $user, 'tab' => 'advertising'])
            ->with('success', 'Offer set to '.\App\Services\Fulfillment\IncentiveCatalog::find($validated['incentive_key'])->name.'.');
    }

    /**
     * Staff record that the incentive certificate actually reached the member,
     * against the provider's reference. Never inferred from display or clicks.
     */
    public function recordIncentiveDelivery(
        Request $request,
        User $user,
        \App\Services\Fulfillment\MemberIncentive $incentive,
    ): RedirectResponse {
        $validated = $request->validate([
            'delivery_method'    => ['required', 'in:email,mail,provider_portal,other'],
            'delivery_reference' => ['required', 'string', 'max:160'],
            'note'               => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $record = $incentive->recordDelivery(
                $user, $request->user(), $validated['delivery_method'],
                $validated['delivery_reference'], $validated['note'] ?? null, $request,
            );
        } catch (\RuntimeException $e) {
            return back()->withErrors(['delivery_reference' => $e->getMessage()]);
        }

        AdminAuditLogService::log(
            actor:     $request->user(),
            action:    'member.incentive.delivery_recorded',
            subject:   $user,
            payload:   ['record' => $record->record_uuid, 'method' => $record->delivery_method, 'reference' => $record->delivery_reference],
            ipAddress: $request->ip(),
        );

        return redirect()->route('admin.members.show', ['user' => $user, 'tab' => 'advertising'])
            ->with('success', 'Incentive delivery recorded.');
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
