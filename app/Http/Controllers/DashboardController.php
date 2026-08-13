<?php

namespace App\Http\Controllers;

use App\Enums\MemberEnquiryStatus;
use App\Enums\MemberOfferStatus;
use App\Enums\UserRole;
use App\Models\ChargebackDispute;
use App\Models\HelpArticle;
use App\Models\LoginSession;
use App\Models\MemberEnquiry;
use App\Models\MemberOffer;
use App\Models\Property;
use App\Models\PropertyView;
use App\Models\SupportChatSession;
use App\Models\SupportTicket;
use App\Models\TermsVersion;
use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Routes /dashboard to a role-aware view:
 *
 *   - Admins (admin / super_admin) see operational signals: pending member
 *     enquiries, recent bookings + charges, suspicious logins, open support
 *     tickets, open disputes, legal version coverage.
 *   - Travelers / hosts / members see their own bookings, recent charges,
 *     and shortcuts to account pages.
 *
 * Counts are bounded (last 7d windows for activity, top 5 rows for recent
 * lists) so a fresh deploy or a million-row table both render in under a
 * second. No N+1: relations are eager-loaded where the view touches them.
 */
class DashboardController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        abort_unless($user, 401);

        if ($user->isAdmin()) {
            return view('dashboard-admin', $this->adminPayload());
        }

        if ($user->role === UserRole::Host) {
            return view('dashboard-host', $this->hostPayload($user));
        }

        if ($user->role === UserRole::Member) {
            return view('dashboard-member', $this->memberPayload($user));
        }

        return view('dashboard-user', $this->userPayload($user));
    }

    /**
     * Listings owned by a host, plus aggregated view stats + geo pins for
     * the listing-analytics panel.
     */
    private function hostPayload(User $user): array
    {
        $listings = Property::query()
            ->where('host_id', $user->id)
            ->orderBy('title')
            ->get();

        // No bookings panel. Vaytoven advertises listings; the funnel signal a
        // host wants is offers on their listings, which the offers dashboard
        // already carries.
        return [
            'me' => $user,
            'listings' => $listings,
        ] + $this->analyticsPayload($listings);
    }

    /**
     * Managed listings tied to enquiries this member submitted (matched by
     * email, since members_enquiries has no user_id FK yet). Members get the
     * same listing-analytics panel as hosts — empty state if no managed
     * listings exist yet.
     */
    private function memberPayload(User $user): array
    {
        $enquiryIds = MemberEnquiry::where('email', $user->email)->pluck('id');

        $listings = Property::query()
            ->whereIn('converted_from_enquiry_id', $enquiryIds)
            ->orderBy('title')
            ->get();

        $myEnquiry = MemberEnquiry::where('email', $user->email)
            ->orderByDesc('created_at')
            ->first();

        // Offers Vaytoven has extended to this member — pending first
        // (most actionable), then a tail of recent responded/expired so
        // the member sees their history without a separate page.
        // toMembers() is load-bearing: buyer submissions also carry this
        // member's id in member_user_id (they're the listing owner who must
        // respond), and without the direction filter they would surface in
        // this outbound-offer panel — whose accept button posts to
        // MemberOfferController, which does not apply the 24-hour expiry
        // check. Inbound submissions belong on /account/listing-offers.
        $offers = MemberOffer::query()
            ->toMembers()
            ->where('member_user_id', $user->id)
            ->with('property:id,title,city,country')
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('sent_at')
            ->limit(10)
            ->get();

        $pendingOfferCount = $offers->where('status', MemberOfferStatus::Pending)->count();

        return [
            'me' => $user,
            'listings' => $listings,
            'myEnquiry' => $myEnquiry,
            'offers' => $offers,
            'pendingOfferCount' => $pendingOfferCount,
        ] + $this->analyticsPayload($listings);
    }

    /**
     * Shared analytics shape for the listing-views panel — used by both the
     * host and member dashboards. Empty listings → empty stats + empty map.
     *
     * @param Collection<int, Property> $listings
     */
    private function analyticsPayload(Collection $listings): array
    {
        if ($listings->isEmpty()) {
            return [
                'totalViews30d'   => 0,
                'totalViews7d'    => 0,
                'uniqueCountries' => 0,
                'perListingStats' => [],
                'pinPoints'       => [],
                'pinsByListing'   => [],
                // The map partial reads these unconditionally, so the empty
                // branch has to supply them too — omitting them 500s the
                // dashboard for any host or member with no listings yet.
                'mapboxToken'     => $this->publicMapboxTokenOrNull(config('services.mapbox.token')),
                'mapboxStyle'     => config('services.mapbox.style'),
            ];
        }

        $ids = $listings->pluck('id')->all();
        $cutoff30 = now()->subDays(30);
        $cutoff7  = now()->subDays(7);

        $totalViews30d = PropertyView::whereIn('property_id', $ids)
            ->where('occurred_at', '>=', $cutoff30)
            ->count();

        $totalViews7d = PropertyView::whereIn('property_id', $ids)
            ->where('occurred_at', '>=', $cutoff7)
            ->count();

        $uniqueCountries = PropertyView::whereIn('property_id', $ids)
            ->where('occurred_at', '>=', $cutoff30)
            ->whereNotNull('country')
            ->distinct('country')
            ->count('country');

        // Per-listing aggregates for the table.
        $perListingStats = [];
        foreach ($listings as $listing) {
            $base = PropertyView::where('property_id', $listing->id);
            $views30 = (clone $base)->where('occurred_at', '>=', $cutoff30)->count();
            $views7  = (clone $base)->where('occurred_at', '>=', $cutoff7)->count();

            $topRow = (clone $base)
                ->where('occurred_at', '>=', $cutoff30)
                ->whereNotNull('city')
                ->selectRaw('city, country, count(*) as c')
                ->groupBy('city', 'country')
                ->orderByDesc('c')
                ->first();

            $perListingStats[$listing->id] = [
                'views_30d'   => $views30,
                'views_7d'    => $views7,
                'top_city'    => $topRow?->city,
                'top_country' => $topRow?->country,
            ];
        }

        // Pin aggregation for the map. Group by (lat, lng) rounded so visits
        // from the same metro stack into one pin rather than scattering.
        $aggregate = fn ($q) => $q
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->selectRaw('round(latitude, 1) as lat, round(longitude, 1) as lng, city, country, count(*) as c')
            ->groupBy('lat', 'lng', 'city', 'country')
            ->orderByDesc('c');

        $shape = fn ($r) => [
            'lat'     => (float) $r->lat,
            'lng'     => (float) $r->lng,
            'city'    => $r->city,
            'country' => $r->country,
            'count'   => (int) $r->c,
        ];

        // Global pins (default map view across all listings).
        $pinPoints = $aggregate(PropertyView::query()
                ->whereIn('property_id', $ids)
                ->where('occurred_at', '>=', $cutoff30))
            ->limit(200)
            ->get()
            ->map($shape)
            ->all();

        // Per-listing pins so clicking a listing row in the table can zoom
        // the map to just that listing's visitor cities (FR-12.x analytics
        // drill-down). Keyed by property_id for cheap JS lookup.
        $pinsByListing = [];
        foreach ($listings as $listing) {
            $pinsByListing[(string) $listing->id] = $aggregate(PropertyView::query()
                    ->where('property_id', $listing->id)
                    ->where('occurred_at', '>=', $cutoff30))
                ->limit(100)
                ->get()
                ->map($shape)
                ->all();
        }

        return [
            'totalViews30d'   => $totalViews30d,
            'totalViews7d'    => $totalViews7d,
            'uniqueCountries' => $uniqueCountries,
            'perListingStats' => $perListingStats,
            'pinPoints'       => $pinPoints,
            'pinsByListing'   => $pinsByListing,
            // Only expose a Mapbox PUBLIC token (pk.*) to the browser. Secret
            // tokens (sk.*) have scopes like manage-uploads / create-tokens
            // and must never leave the server — if env contains one, fall
            // back to OSM and log so ops can rotate it. Tokens are otherwise
            // designed for client-side use, restricted by URL referrer in the
            // Mapbox dashboard.
            'mapboxToken'     => $this->publicMapboxTokenOrNull(config('services.mapbox.token')),
            'mapboxStyle'     => config('services.mapbox.style'),
        ];
    }

    private function publicMapboxTokenOrNull(?string $token): ?string
    {
        if (! $token) {
            return null;
        }
        if (str_starts_with($token, 'pk.')) {
            return $token;
        }
        // sk.* (secret) or anything else — don't ship to the browser.
        \Illuminate\Support\Facades\Log::warning(
            'mapbox: MAPBOX_API does not begin with pk.* — refusing to expose. Falling back to OSM tiles. Rotate at https://account.mapbox.com/access-tokens/'
        );
        return null;
    }

    private function adminPayload(): array
    {
        return [
            // Member enquiries — the conversion funnel for the managed program.
            'enquiriesNew' => MemberEnquiry::where('status', MemberEnquiryStatus::New)->count(),
            'enquiriesRecent' => MemberEnquiry::orderByDesc('created_at')->limit(5)->get(),

            // Offers — the actual funnel. What travelers send and what listing
            // owners do with it, in place of the booking counts that used to
            // sit here.
            'offersByStatus' => MemberOffer::query()
                ->selectRaw('status, count(*) as c')
                ->groupBy('status')
                ->pluck('c', 'status'),
            'offersRecent' => MemberOffer::with('property:id,title')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(),

            // Support — agent + human ticketing load.
            'ticketsOpen' => SupportTicket::where('status', 'open')->count(),
            'chatSessions24h' => SupportChatSession::where('created_at', '>=', now()->subDay())->count(),

            // Risk — fraud + chargeback signals.
            'suspiciousLogins24h' => LoginSession::where('is_suspicious', true)
                ->where('occurred_at', '>=', now()->subDay())
                ->count(),
            'disputesOpen' => ChargebackDispute::whereNotIn('status', ['won', 'lost'])->count(),

            // Tracking — events firing means the SDK is alive.
            'trackingEvents24h' => TrackingEvent::where('occurred_at', '>=', now()->subDay())->count(),

            // Legal — counsel + adoption visibility.
            'legalVersions' => TermsVersion::query()
                ->whereNull('superseded_at')
                ->orderBy('kind')
                ->get(),

            // Library — content team coverage.
            'helpArticleCount' => HelpArticle::published()->count(),

            // User counts by role — growth + role mix.
            'usersByRole' => User::query()
                ->selectRaw('role, count(*) as c')
                ->groupBy('role')
                ->pluck('c', 'role'),
        ];
    }

    private function userPayload(User $user): array
    {
        // Offers this traveler has SENT, in place of bookings and charges.
        // charges rows hang off booking_id with no user_id of their own, so
        // they only ever described money taken for a stay — which Vaytoven
        // does not take. What a traveler actually has here is submissions
        // waiting on a listing owner.
        $offers = MemberOffer::query()
            ->where('buyer_user_id', $user->id)
            ->with('property:id,title,city')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return [
            'me' => $user,
            'offers' => $offers,
            'openOfferCount' => $offers
                ->filter(fn (MemberOffer $o) => $o->effectiveStatus() === MemberOfferStatus::Pending)
                ->count(),
        ];
    }
}
