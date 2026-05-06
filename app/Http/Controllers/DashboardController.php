<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\MemberEnquiryStatus;
use App\Models\Booking;
use App\Models\Charge;
use App\Models\ChargebackDispute;
use App\Models\HelpArticle;
use App\Models\LoginSession;
use App\Models\MemberEnquiry;
use App\Models\Refund;
use App\Models\SupportChatSession;
use App\Models\SupportTicket;
use App\Models\TermsVersion;
use App\Models\TrackingEvent;
use App\Models\User;
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

        return view('dashboard-user', $this->userPayload($user));
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
