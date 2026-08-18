<?php

namespace App\Http\Controllers;
use App\Enums\ActivityType;
use App\Services\Tracking\ActivityRecorder;

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
            checkIn: $request->validated('check_in'),
            checkOut: $request->validated('check_out'),
            guests: $request->validated('guests') ? (int) $request->validated('guests') : null,
        );

        app(ActivityRecorder::class)->record(
            ActivityType::OfferSubmitted,
            $request,
            subjectType: 'offer',
            subjectReference: $offer->reference,
            result: 'completed',
            metadata: ['property' => $property->reference],
        );

        // Deliberately explicit that nothing has been reserved and nothing has
        // been charged. Vaytoven advertises the listing; the stay itself is
        // arranged directly between the visitor and the listing member.
        return back()
            ->with('offer_reference', $offer->reference ?? null)
            ->with('offer_expires_at', $offer->expires_at?->format('D j M Y \a\t g:ia'))
            ->with('success', 'Your offer has been submitted to the listing member for review. '
                .'This is not a confirmed reservation. You will be notified if the listing member '
                .'accepts or responds to your request.');
    }

    /**
     * GET /account/offers — everything submitted against listings this user
     * owns. This is the Buyer | Listing | Amount | Date | Time | IP | Status |
     * Expires table.
     */
    public function index(Request $request): View
    {
        $owner = $request->user();

        $offers = MemberOffer::query()
            ->fromBuyers()
            ->forListingsOwnedBy($owner)
            ->with(['buyer:id,name,email', 'property:id,title,city,country'])
            ->orderByDesc('sent_at')
            ->paginate(25)
            ->withQueryString();

        // Stamp the first time the owner sees each one. This is what lets
        // staff answer "did they ever look at it?" — an offer that lapsed
        // unopened and one that was read and ignored are different failures
        // and need different conversations.
        foreach ($offers as $offer) {
            $offer->markViewed();
        }

        return view('offers.index', ['offers' => $offers]);
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
