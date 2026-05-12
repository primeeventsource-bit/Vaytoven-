<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\MemberEnquiryStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Charge;
use App\Models\ChargebackDispute;
use App\Models\HelpArticle;
use App\Models\LoginSession;
use App\Models\MemberEnquiry;
use App\Models\Property;
use App\Models\PropertyView;
use App\Models\Refund;
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

        return [
            'me' => $user,
            'listings' => $listings,
            'myEnquiry' => $myEnquiry,
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
        $pinPoints = PropertyView::query()
            ->whereIn('property_id', $ids)
            ->where('occurred_at', '>=', $cutoff30)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->selectRaw('round(latitude, 1) as lat, round(longitude, 1) as lng, city, country, count(*) as c')
            ->groupBy('lat', 'lng', 'city', 'country')
            ->orderByDesc('c')
            ->limit(200)
            ->get()
            ->map(fn ($r) => [
                'lat'     => (float) $r->lat,
                'lng'     => (float) $r->lng,
                'city'    => $r->city,
                'country' => $r->country,
                'count'   => (int) $r->c,
            ])
            ->all();

        return [
            'totalViews30d'   => $totalViews30d,
            'totalViews7d'    => $totalViews7d,
            'uniqueCountries' => $uniqueCountries,
            'perListingStats' => $perListingStats,
            'pinPoints'       => $pinPoints,
        ];
    }

    private function adminPayload(): array
    {
        $sevenDaysAgo = now()->subDays(7);

        return [
            // Member enquiries — the conversion funnel for the managed program.
            'enquiriesNew' => MemberEnquiry::where('status', MemberEnquiryStatus::New)->count(),
            'enquiriesRecent' => MemberEnquiry::orderByDesc('created_at')->limit(5)->get(),

            // Bookings — operational health, what's in flight today.
            'bookingsByStatus' => Booking::query()
                ->selectRaw('status, count(*) as c')
                ->groupBy('status')
                ->pluck('c', 'status'),
            'bookingsRecent' => Booking::with('property:id,title')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(),

            // Payments — money flow at a glance.
            'chargesLast7dCents' => (int) Charge::where('created_at', '>=', $sevenDaysAgo)->sum('amount_cents'),
            'refundsLast7dCents' => (int) Refund::where('created_at', '>=', $sevenDaysAgo)->sum('amount_cents'),

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
        $bookings = Booking::query()
            ->where('traveler_id', $user->id)
            ->with('property:id,title')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $charges = Charge::query()
            ->whereIn('booking_id', $bookings->pluck('id'))
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return [
            'me' => $user,
            'bookings' => $bookings,
            'charges' => $charges,
            'upcomingCount' => $bookings
                ->where('status', BookingStatus::Confirmed)
                ->where('check_in_date', '>=', now())
                ->count(),
        ];
    }
}
