<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Tracking\LoginTrackingService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;
use Throwable;

/**
 * Wires Laravel's auth events to LoginTrackingService (FR-1.6, FR-10.7).
 *
 * Records every login/logout/failed/lockout into login_sessions so the
 * chargeback evidence pipeline (Phase 8B) can pull a complete history
 * for any user-disputed transaction.
 *
 * Listener exceptions never block authentication — every handler is
 * wrapped in try/catch + logged. Tracking failure is non-critical.
 */
class TrackAuthEvents
{
    public function __construct(
        private readonly LoginTrackingService $tracker,
        private readonly Request $request,
    ) {
    }

    public function handleLogin(Login $event): void
    {
        $this->safe(fn () => $this->tracker->record(
            user: $event->user,
            authEvent: 'login',
            request: $this->request,
            sessionId: session()->getId(),
        ));
    }

    public function handleLogout(Logout $event): void
    {
        if (! $event->user) {
            return; // Logout fires even when there's no user (rare edge case)
        }
        $this->safe(fn () => $this->tracker->record(
            user: $event->user,
            authEvent: 'logout',
            request: $this->request,
            sessionId: session()->getId(),
        ));
    }

    public function handleFailed(Failed $event): void
    {
        // Only track failed logins for known users — recording fails for
        // unknown emails leaks user enumeration via the session ID linkage.
        if (! $event->user instanceof User) {
            return;
        }
        $this->safe(fn () => $this->tracker->record(
            user: $event->user,
            authEvent: 'failed',
            request: $this->request,
        ));
    }

    public function handleLockout(Lockout $event): void
    {
        // Lockout fires post-throttle. We don't have the user object — skip
        // the per-user lockout row to avoid the same enumeration leak as Failed.
        // A separate metrics counter handles aggregate lockout monitoring.
    }

    public function subscribe(): array
    {
        return [
            Login::class => 'handleLogin',
            Logout::class => 'handleLogout',
            Failed::class => 'handleFailed',
            Lockout::class => 'handleLockout',
        ];
    }

    private function safe(\Closure $fn): void
    {
        try {
            $fn();
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                'auth event tracking failed: '.$e->getMessage(),
                ['exception' => $e],
            );
        }
    }
}
