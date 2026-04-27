<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Allow request only if the authenticated user has the `admin` or `super_admin` role.
     * Pair with `auth:sanctum` upstream so $request->user() is populated.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isAdmin()) {
            return response()->json([
                'message' => 'Forbidden — admin role required.',
            ], 403);
        }

        return $next($request);
    }
}
