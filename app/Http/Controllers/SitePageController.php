<?php

namespace App\Http\Controllers;

use App\Enums\PropertyStatus;
use App\Models\HelpArticle;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The public marketing/company pages that replaced the footer's `href="#"`
 * placeholders.
 *
 * Each one is backed by real data where real data exists — destinations are
 * derived from published listings, host resources come from the help-article
 * table — rather than being a static page that happens to occupy a URL.
 */
class SitePageController extends Controller
{
    /**
     * Destinations, aggregated from published listings.
     *
     * Nothing is hardcoded: a city appears here because properties exist in
     * it, and disappears when the last one is unpublished. That means the page
     * can never advertise a destination with nothing to book.
     */
    public function destinations(Request $request): View
    {
        $search = trim($request->string('q')->toString());

        $query = Property::query()
            ->where('status', PropertyStatus::Active->value)
            ->selectRaw('city, country, COUNT(*) as listing_count, MIN(base_nightly_cents) as from_cents')
            ->groupBy('city', 'country')
            ->orderByDesc('listing_count')
            ->orderBy('city');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('city', 'like', "%{$search}%")->orWhere('country', 'like', "%{$search}%");
            });
        }

        $destinations = $query->get();

        // One representative listing (with its first photo) per destination,
        // fetched in a single pass rather than a query per card.
        $covers = Property::query()
            ->where('status', PropertyStatus::Active->value)
            ->whereIn('city', $destinations->pluck('city'))
            ->with(['photos' => fn ($q) => $q->orderBy('sort_order')])
            ->get(['id', 'city', 'title'])
            ->groupBy('city');

        return view('site.destinations', [
            'destinations' => $destinations,
            'covers' => $covers,
            'search' => $search,
        ]);
    }

    public function about(): View
    {
        return view('site.about');
    }

    public function mobileApp(): View
    {
        return view('site.mobile-app');
    }

    /**
     * Host resources, rendered from the help-article table filtered to the
     * host audience — so operators maintain them in one place instead of
     * this page being a second, drifting copy of the same guidance.
     */
    public function hostResources(): View
    {
        $articles = HelpArticle::query()
            ->published()
            ->whereIn('audience', ['host', 'all'])
            ->orderBy('category')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('category');

        return view('site.host-resources', ['grouped' => $articles]);
    }

    public function earningsCalculator(): View
    {
        return view('site.earnings-calculator', [
            // The calculator subtracts the same host commission the booking
            // flow charges, read from settings rather than duplicated as a
            // literal, so an operator changing the fee changes the estimate.
            'hostFeePercent' => (int) setting('fees.host_commission_pct', 3),
        ]);
    }
}
