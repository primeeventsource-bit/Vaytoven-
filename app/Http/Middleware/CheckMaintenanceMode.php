<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin-toggled maintenance mode (setting general.maintenance_mode) — the
 * operator's kill switch in the Settings console, distinct from Laravel's
 * deploy-time `php artisan down`.
 *
 * Admins keep full access (they need to be able to turn it back off), and
 * the auth + health + webhook surfaces stay open: payment processors keep
 * webhook delivery working and ops can still sign in.
 */
class CheckMaintenanceMode
{
    private const OPEN_PATHS = [
        'login', 'logout', 'up', 'health',
        'webhooks/*', 'admin', 'admin/*',
        'forgot-password', 'reset-password/*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! setting('general.maintenance_mode', false)) {
            return $next($request);
        }

        if ($request->user()?->isAdmin()) {
            return $next($request);
        }

        if ($request->is(...self::OPEN_PATHS)) {
            return $next($request);
        }

        $message = (string) setting(
            'general.maintenance_message',
            'We are briefly down for maintenance and will be right back.',
        );

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 503);
        }

        return response()->view('maintenance', ['message' => $message], 503);
    }
}
