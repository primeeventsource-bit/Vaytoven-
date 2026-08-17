<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MemberOffer;
use App\Models\MemberServiceOrder;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * One search box over everything staff need to find.
 *
 * The realistic use is a phone ringing: somebody reads out an email, a phone
 * number, an offer reference or a transaction id, and whoever answers needs
 * the record before the caller finishes the sentence. So the box takes all of
 * them and works out what it was given, rather than making staff pick a
 * category first.
 */
class SearchController extends Controller
{
    private const PER_TYPE = 10;

    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));

        // Two characters normally, but a single DIGIT is allowed: member #7 is
        // a real thing to search for, an exact id lookup is cheap, and the
        // minimum exists to stop one letter matching half the database — which
        // a number does not do.
        $searchable = strlen($term) >= 2 || ($term !== '' && ctype_digit($term));

        return view('admin.search.index', [
            'term'    => $term,
            'results' => $searchable ? $this->search($term) : null,
        ]);
    }

    /**
     * @return array<string, \Illuminate\Support\Collection>
     */
    private function search(string $term): array
    {
        $like = '%'.$term.'%';

        // Phone numbers get typed with spaces, dashes and brackets that are
        // rarely stored the same way. Comparing the digits alone is what makes
        // "(877) 782-9868" find a row saved as "+18777829868".
        $digits = preg_replace('/\D+/', '', $term);

        return [
            'members' => User::query()
                ->where(function ($q) use ($like, $digits, $term) {
                    $q->where('name', 'like', $like)
                      ->orWhere('email', 'like', $like)
                      ->orWhere('phone', 'like', $like);

                    if (strlen($digits) >= 6) {
                        // Strip the stored value down to digits too, so the
                        // comparison is like-for-like in both directions.
                        $q->orWhereRaw(
                            "REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', '') LIKE ?",
                            ['%'.$digits.'%'],
                        );
                    }

                    if (ctype_digit($term)) {
                        $q->orWhere('id', (int) $term);
                    }
                })
                ->orderBy('name')
                ->limit(self::PER_TYPE)
                ->get(['id', 'name', 'email', 'phone', 'role']),

            'properties' => Property::query()
                ->with('host:id,name,email')
                ->where(function ($q) use ($like, $term) {
                    $q->where('title', 'like', $like)
                      ->orWhere('city', 'like', $like)
                      ->orWhere('region', 'like', $like);

                    if (ctype_digit($term)) {
                        $q->orWhere('id', (int) $term);
                    }
                })
                ->orderBy('title')
                ->limit(self::PER_TYPE)
                ->get(),

            'offers' => MemberOffer::query()
                ->with(['property:id,title', 'buyer:id,name,email', 'member:id,name'])
                ->where(function ($q) use ($like) {
                    $q->where('reference', 'like', $like)
                      ->orWhereHas('buyer', fn ($b) => $b->where('email', 'like', $like)
                          ->orWhere('name', 'like', $like))
                      ->orWhereHas('property', fn ($p) => $p->where('title', 'like', $like));
                })
                ->orderByDesc('created_at')
                ->limit(self::PER_TYPE)
                ->get(),

            'orders' => MemberServiceOrder::query()
                ->where(function ($q) use ($like) {
                    // The NMI transaction id is here on purpose: when a
                    // processor opens a dispute, that number is all anyone has
                    // to go on, and hunting for it by hand is the slow part.
                    $q->where('reference', 'like', $like)
                      ->orWhere('nmi_transaction_id', 'like', $like)
                      ->orWhere('email', 'like', $like)
                      ->orWhere('last_name', 'like', $like);
                })
                ->orderByDesc('created_at')
                ->limit(self::PER_TYPE)
                ->get(),
        ];
    }
}
