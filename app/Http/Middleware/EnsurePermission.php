<?php

namespace App\Http\Middleware;


use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Granular RBAC gate: `permission:properties.edit`.
 *
 * Multiple keys are OR'd, matching Laravel's own `can:` convention —
 * `permission:properties.edit,properties.publish` passes if the user holds
 * either. Compose with `admin` when a route should also require staff
 * standing; on its own this middleware only asks "does the user hold the
 * permission", which is what lets non-admin roles like Property Manager
 * reach their modules.
 *
 * Super admins always pass (User::hasPermission short-circuits), and while
 * RBAC is unseeded the check falls back to the legacy admin gate so the
 * console never dark-ships — see Role::configured().
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Authentication required.');
        }

        // A deactivated account keeps its roles but must not act on them.
        if (! $user->isActive()) {
            abort(403, 'This account is deactivated.');
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        abort(403, 'You do not have permission to perform this action.');
    }
}
