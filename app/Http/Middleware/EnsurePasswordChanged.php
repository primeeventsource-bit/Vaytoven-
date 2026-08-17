<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Holds a signed-in user on the change-password screen until they set their
 * own password.
 *
 * Applied broadly rather than to a handful of sensitive routes: an account
 * still on a staff-issued credential should not be able to do ANYTHING under
 * that credential, not merely be kept out of the admin console.
 */
class EnsurePasswordChanged
{
    /** Routes that must stay reachable, or the user is locked in a loop. */
    private const ALLOWED = [
        'password.first-change',
        'password.first-change.store',
        'logout',
        'health',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->must_change_password) {
            return $next($request);
        }

        if (in_array($request->route()?->getName(), self::ALLOWED, true)) {
            return $next($request);
        }

        // An API client gets a machine-readable refusal rather than a redirect
        // into an HTML form it cannot render.
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'You must set a new password before using this account.',
                'error'   => 'password_change_required',
            ], 423);   // Locked
        }

        return redirect()->route('password.first-change');
    }
}
