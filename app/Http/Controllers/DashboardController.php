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
use App\Services\Analytics\ListingAnalytics;
use App\Services\Analytics\MemberEngagementMap;
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
            'engagement' => $this->engagementMap($listings),
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

        // A member's listings arrive by two routes, and this only looked for
        // one of them.
        //
        // Converting an enquiry stamps converted_from_enquiry_id. Building a
        // listing in the admin builder does not — it sets host_id, the owner —
        // so every listing staff created for a member was invisible on that
        // member's own dashboard: no title, no view counts, no engagement map.
        // The host dashboard had always queried host_id, which is why the same
        // listing appeared there and not here.
        //
        // Both routes now count. Ownership is the primary claim; the enquiry
        // link stays so listings converted before host_id was being set are
        // not dropped.
        $listings = Property::query()
            ->where(function ($q) use ($user, $enquiryIds) {
                $q->where('host_id', $user->id);

                if ($enquiryIds->isNotEmpty()) {
                    $q->orWhereIn('converted_from_enquiry_id', $enquiryIds);
                }
            })
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
            'engagement' => $this->engagementMap($listings),
        ] + $this->analyticsPayload($listings);
    }

    /**
     * Shared analytics shape for the listing-views panel.
     *
     * Delegates to ListingAnalytics so the host dashboard, the member
     * dashboard and the admin activity screen report the same numbers for the
     * same listing. Two copies of "views in the last 30 days" drift, and then
     * a host and an admin disagree about the same property.
     *
     * @param Collection<int, Property> $listings
     */
    private function analyticsPayload(Collection $listings): array
    {
        return app(ListingAnalytics::class)->payload($listings);
    }

    /**
     * The privacy-safe engagement map a member or host sees.
     *
     * Deliberately a different service from the admin analytics: this one
     * emits approximate cities and counts and nothing else. Login IPs,
     * contract IPs, device details and payment history stay on the admin side.
     */
    private function engagementMap(Collection $listings): array
    {
        $days = (int) request()->query('engagement_days', 30);
        $days = array_key_exists($days, MemberEngagementMap::WINDOWS) ? $days : 30;

        $propertyId = request()->integer('engagement_property') ?: null;

        return app(MemberEngagementMap::class)->build($listings, $days, $propertyId);
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
