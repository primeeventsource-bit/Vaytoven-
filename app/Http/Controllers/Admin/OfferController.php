<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MemberOfferStatus;
use App\Enums\OfferKind;
use App\Http\Controllers\Controller;
use App\Models\MemberOffer;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Cross-platform view of every buyer inquiry and offer, gated on
 * `offers.view`. Unlike the owner dashboard this is not scoped to any
 * listing — it exists so an administrator can answer "what was submitted,
 * by whom, from where, and when did it lapse" for any listing on the site.
 */
class OfferController extends Controller
{
    private const PER_PAGE = 50;

    /**
     * One offer, in full, with everything that happened to it.
     *
     * The register answers "what came in"; this answers "what happened to this
     * one", which is the question staff actually get on the phone.
     */
    public function show(MemberOffer $offer): View
    {
        $offer->load([
            'buyer:id,name,email,phone',
            'member:id,name,email,phone',
            'property:id,title,city,country,host_id',
            'sentBy:id,name,email',
        ]);

        return view('admin.offers.show', [
            'offer'    => $offer,
            'timeline' => $this->timeline($offer),
        ]);
    }

    /**
     * @return array<int, array{at: \Illuminate\Support\Carbon, label: string, detail: ?string}>
     */
    private function timeline(MemberOffer $offer): array
    {
        $events = [];

        $push = function (?object $at, string $label, ?string $detail = null) use (&$events) {
            if ($at) {
                $events[] = ['at' => $at, 'label' => $label, 'detail' => $detail];
            }
        };

        $push($offer->sent_at ?? $offer->created_at, 'Submitted',
            $offer->submitted_ip ? 'from '.$offer->submitted_ip : null);

        $push($offer->viewed_at, 'Opened by the listing member');

        $push($offer->responded_at,
            match ($offer->status) {
                MemberOfferStatus::Accepted => 'Accepted by the listing member',
                MemberOfferStatus::Declined => 'Declined by the listing member',
                default                     => 'Responded to',
            },
            $offer->member_response_notes);

        // Expiry is a fact about the clock, not an action anyone took, so it
        // is only shown once it has actually happened.
        if ($offer->expires_at && $offer->expires_at->isPast() && ! $offer->responded_at) {
            $push($offer->expires_at, 'Expired unanswered',
                $offer->viewed_at ? 'It had been opened' : 'It was never opened');
        }

        usort($events, fn ($a, $b) => $a['at'] <=> $b['at']);

        return $events;
    }

    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $kind = $request->string('kind')->toString();
        $search = $request->string('q')->toString();

        $query = MemberOffer::query()
            ->fromBuyers()
            ->with([
                'buyer:id,name,email',
                'member:id,name,email',
                'property:id,title,city,country,host_id',
            ]);

        if ($status !== '' && MemberOfferStatus::tryFrom($status)) {
            $query->where('status', $status);
        }

        if ($kind !== '' && OfferKind::tryFrom($kind)) {
            $query->where('kind', $kind);
        }

        if ($search !== '') {
            // Buyer name/email, or the listing title.
            $query->where(function ($q) use ($search) {
                $q->whereHas('buyer', fn ($b) => $b
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"))
                    ->orWhereHas('property', fn ($p) => $p->where('title', 'like', "%{$search}%"));
            });
        }

        return view('admin.offers.index', [
            'offers' => $query->orderByDesc('sent_at')->paginate(self::PER_PAGE)->withQueryString(),
            'counts' => [
                'total' => MemberOffer::query()->fromBuyers()->count(),
                'open' => MemberOffer::query()->fromBuyers()->open()->count(),
                'accepted' => MemberOffer::query()->fromBuyers()->where('status', MemberOfferStatus::Accepted->value)->count(),
                'expired' => MemberOffer::query()->fromBuyers()->where('status', MemberOfferStatus::Expired->value)->count(),
            ],
            'filters' => ['status' => $status, 'kind' => $kind, 'q' => $search],
        ]);
    }
}
