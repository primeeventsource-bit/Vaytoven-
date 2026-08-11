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
