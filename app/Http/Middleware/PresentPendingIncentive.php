<?php

namespace App\Http\Middleware;

use App\Services\Fulfillment\MemberIncentive;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends a member who has just signed in to the incentive screen first.
 *
 * Only when the login listener flagged the session, only for page loads, and
 * never on the routes a member needs to get through first (terms, a forced
 * password change, verification) or to leave (logout).
 */
class PresentPendingIncentive
{
    private const PASS_THROUGH = [
        'member.incentive.show', 'member.incentive.acknowledge', 'member.incentive.reward',
        'logout', 'legal.*', 'password.*', 'verification.*', 'first-password.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET')
            || $request->expectsJson()
            || ! $request->hasSession()
            || ! $request->session()->get(MemberIncentive::SESSION_PENDING)
            || $request->routeIs(...self::PASS_THROUGH)) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user || $user->isStaff()) {
            $request->session()->forget(MemberIncentive::SESSION_PENDING);

            return $next($request);
        }

        return redirect()->route('member.incentive.show');
    }
}
