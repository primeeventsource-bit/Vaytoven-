<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOfferRequest;
use App\Models\MemberOffer;
use App\Models\Property;
use App\Services\Offers\OfferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Buyer-side submission and listing-owner-side response for inquiries and
 * offers on property listings.
 *
 * The owner dashboard is scoped strictly to listings the signed-in user owns
 * (`properties.host_id`). Staff never see other people's offers here — the
 * cross-platform view lives in Admin\OfferController behind `offers.view`.
 */
class OfferController extends Controller
{
    public function __construct(private readonly OfferService $offers) {}

    /** POST /properties/{property}/offers — a buyer submits. */
    public function store(StoreOfferRequest $request, Property $property): RedirectResponse
    {
        $buyer = $request->user();

        if ($property->host_id === $buyer->id) {
            return back()->with('error', 'You cannot submit an offer on your own listing.');
        }

        $offer = $this->offers->submit(
            property: $property,
            buyer: $buyer,
            kind: $request->kind(),
            amountCents: $request->amountCents(),
            message: $request->string('message')->toString() ?: null,
            ipAddress: $request->ip(),
        );

        return back()->with('success', sprintf(
            'Your %s has been sent to the listing owner. It expires %s.',
            strtolower($offer->kind->label()),
            $offer->expires_at?->format('D j M \a\t g:ia') ?? 'in 24 hours',
        ));
    }

    /**
     * GET /account/offers — everything submitted against listings this user
     * owns. This is the Buyer | Listing | Amount | Date | Time | IP | Status |
     * Expires table.
     */
    public function index(Request $request): View
    {
        $owner = $request->user();

        return view('offers.index', [
            'offers' => MemberOffer::query()
                ->fromBuyers()
                ->forListingsOwnedBy($owner)
                ->with(['buyer:id,name,email', 'property:id,title,city,country'])
                ->orderByDesc('sent_at')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function accept(Request $request, MemberOffer $offer): RedirectResponse
    {
        $this->authoriseOwner($request, $offer);

        $this->offers->accept($offer, $request->user(), $request->string('notes')->toString(), $request->ip());

        return back()->with('success', 'Offer accepted. The buyer has been recorded as accepted.');
    }

    public function decline(Request $request, MemberOffer $offer): RedirectResponse
    {
        $this->authoriseOwner($request, $offer);

        $this->offers->decline($offer, $request->user(), $request->string('notes')->toString(), $request->ip());

        return back()->with('success', 'Offer declined.');
    }

    /**
     * Only the owner of the listing may respond, and only while the offer is
     * genuinely still open. Expiry is evaluated read-through so an offer that
     * timed out a second ago cannot be actioned even if the sweep has not run.
     */
    private function authoriseOwner(Request $request, MemberOffer $offer): void
    {
        $user = $request->user();

        abort_unless($offer->isFromBuyer(), 404);
        abort_unless($user && $offer->property?->host_id === $user->id, 403);
        abort_unless($offer->isAwaitingOwner(), 422, 'This offer is no longer open.');
    }
}
