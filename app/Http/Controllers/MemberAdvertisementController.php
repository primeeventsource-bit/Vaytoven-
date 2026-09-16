<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Services\Fulfillment\AdvertisementAcknowledgement;
use App\Services\Fulfillment\AdvertisementFulfillment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * The member's own view of an advertisement they paid for, and where they
 * accept it.
 *
 * Only the signed-in, non-staff owner gets past the door. Staff checking a
 * member's advertisement use the admin screens, and nothing they do here or
 * there is ever recorded as the member's access.
 */
class MemberAdvertisementController extends Controller
{
    public function __construct(private readonly AdvertisementFulfillment $fulfillment)
    {
    }

    public function show(Request $request, Property $property): View
    {
        $member = $request->user();

        abort_unless($this->fulfillment->isOwningMember($member, $property), 404);

        // Opening this page IS accessing the advertisement. Recorded before
        // the state is read so the page reflects it.
        $this->fulfillment->recordAccess($request, $property, $member, review: true);

        return view('member-advertisements.show', [
            'property'        => $property,
            'state'           => $this->fulfillment->state($property->loadMissing('host')),
            'running'         => $this->fulfillment->isRunning($property),
            'acknowledgement' => AdvertisementAcknowledgement::class,
        ]);
    }

    public function accept(Request $request, Property $property): RedirectResponse
    {
        try {
            $this->fulfillment->accept($request, $property, $request->user());
        } catch (RuntimeException $e) {
            abort_unless($this->fulfillment->isOwningMember($request->user(), $property), 404);

            return redirect()->route('member.advertisements.show', $property)
                ->withErrors(['accept' => $e->getMessage()]);
        }

        return redirect()->route('member.advertisements.show', $property)
            ->with('success', 'Thank you — your acceptance of this advertisement has been recorded.');
    }
}
