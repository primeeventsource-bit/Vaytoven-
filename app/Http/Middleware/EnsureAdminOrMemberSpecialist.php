<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the Members Enquiries queue and support-ticket views: admins,
 * super-admins, and member specialists pass; anyone else gets 403.
 *
 * Specialists deliberately do NOT have isAdmin() === true — that boundary
 * stays clean — so a separate middleware is needed for any route that
 * legitimately exposes member/support workflows to specialists.
 */
class EnsureAdminOrMemberSpecialist
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || (! $user->isAdmin() && ! $user->isMemberSpecialist())) {
            abort(403, 'Admin or member-specialist access required.');
        }

        return $next($request);
    }
}
