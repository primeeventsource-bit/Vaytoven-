<?php

namespace App\Http\Controllers;

use App\Enums\MemberOfferStatus;
use App\Models\MemberOffer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Member offer accept / decline. Both endpoints are POSTs from the member
 * dashboard's offer card. Only the offer recipient (member_user_id) can
 * act; only Pending offers are actionable.
 */
class MemberOfferController extends Controller
{
    public function accept(Request $request, MemberOffer $offer): RedirectResponse
    {
        $this->authoriseMemberOnPending($request, $offer);

        $offer->update([
            'status'                => MemberOfferStatus::Accepted,
            'responded_at'          => now(),
            'member_response_notes' => $request->string('notes')->toString() ?: null,
        ]);

        return back()->with(
            'success',
            'Offer accepted. Coordinate with your home program for the dates above, then reply to your member specialist with the confirmation number.'
        );
    }

    public function decline(Request $request, MemberOffer $offer): RedirectResponse
    {
        $this->authoriseMemberOnPending($request, $offer);

        $offer->update([
            'status'                => MemberOfferStatus::Declined,
            'responded_at'          => now(),
            'member_response_notes' => $request->string('notes')->toString() ?: null,
        ]);

        return back()->with('success', 'Offer declined. Your member specialist will be in touch with revised options.');
    }

    /**
     * Belt-and-braces gate: only the offer's member can act, and only on a
     * still-Pending offer. 403 / 422 if either fails.
     */
    private function authoriseMemberOnPending(Request $request, MemberOffer $offer): void
    {
        $user = $request->user();
        abort_unless($user && $offer->member_user_id === $user->id, 403);
        abort_unless($offer->status->isActionable(), 422, 'This offer is no longer pending.');
    }
}
