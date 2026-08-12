<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the guest-facing stay checkout — the funnel that created a booking and
 * charged a card for the rental itself.
 *
 * Vaytoven is a SaaS advertising and marketing platform. It does not act as a
 * booking platform, collect funds for rentals, or process payments between
 * travelers and property owners; its merchant account is for Vaytoven's own
 * advertising, listing, membership and platform fees only. A live endpoint
 * that charges a visitor for someone else's stay contradicts that, and
 * contradicts the published Terms.
 *
 * The routes are kept rather than deleted: the booking records already taken
 * must stay readable, and a managed-booking product may exist later under a
 * different arrangement. They are simply unreachable while
 * `booking.stay_checkout_enabled` is false, which is its default.
 *
 * 404 rather than 403 — from a visitor's point of view this functionality does
 * not exist, and saying "forbidden" implies it might be available to someone.
 */
class EnsureStayCheckoutEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(setting('booking.stay_checkout_enabled', false), 404);

        return $next($request);
    }
}
