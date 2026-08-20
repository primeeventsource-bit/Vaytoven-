<?php

namespace App\Listeners;

use App\Mail\MemberFirstSignIn;
use App\Models\User;
use App\Enums\ActivityType;
use App\Services\GeoIp\GeoIpService;
use App\Services\Tracking\ActivityRecorder;
use App\Services\Tracking\LoginTrackingService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use App\Support\Mail\MailDeliverability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
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
        private readonly ActivityRecorder $activity,
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

        $this->safe(fn () => $this->activity->record(
            ActivityType::LoginSucceeded,
            $this->request,
            subjectType: 'user',
            subjectReference: (string) $event->user?->getAuthIdentifier(),
            result: 'successful',
        ));

        // Cache "last seen" on the user row so the admin user-mgmt table
        // can sort + display without joining login_sessions. login_sessions
        // remains the audit source of truth; this is a fast-query view.
        if ($event->user instanceof User) {
            // Read BEFORE last_login_at is written: a null there is the only
            // signal that this is the first time the account has been used.
            $isFirstSignIn = $event->user->last_login_at === null;

            $this->safe(fn () => $event->user->forceFill(['last_login_at' => now()])->saveQuietly());

            if ($isFirstSignIn) {
                $this->safe(fn () => $this->notifyOfficeOfFirstSignIn($event->user));
            }
        }
    }

    /**
     * Tell the office the member has used the account for the first time.
     *
     * That sign-in is the moment fulfilment completes: the member paid, an
     * account was issued, and it has now reached them. Until it happens nobody
     * knows whether the credentials ever arrived, and that gap is where a
     * member goes quiet and later disputes the charge.
     *
     * Sent to the office address ONLY. The member gets nothing: a "you signed
     * in" email tells them nothing they do not already know, and an alert about
     * an action they just performed reads as a warning that somebody else did
     * it.
     *
     * Sends only when mail is actually deliverable, so a misconfigured
     * environment does not throw inside a login. The whole call is wrapped in
     * safe() by the caller regardless — nothing here may block signing in.
     */
    private function notifyOfficeOfFirstSignIn(User $user): void
    {
        if (! MailDeliverability::isDeliverable()) {
            return;
        }

        Mail::send(new MemberFirstSignIn(
            member: $user,
            context: $this->signInContext(),
        ));
    }

    /**
     * Where and how the sign-in happened.
     *
     * "The account was used" and "the account was used from an iPhone in
     * Orlando" are different facts, and the second is the one worth having if
     * the charge is ever questioned. Everything here comes from the same
     * sources the activity log and login_sessions already use, so the email and
     * the audit trail cannot disagree.
     *
     * The location is a GeoIP estimate and is labelled as one wherever it is
     * shown. It is a rough area, not where a person was.
     *
     * @return array<string, mixed>
     */
    private function signInContext(): array
    {
        $userAgent = $this->request->userAgent();
        $ip        = $this->request->ip();

        $geo = null;

        try {
            $geo = app(GeoIpService::class)->lookup($ip);
        } catch (Throwable) {
            // A geo lookup failing must not cost us the notification: knowing
            // the member signed in matters more than knowing roughly where.
        }

        $place = collect([$geo?->city, $geo?->region, $geo?->country])
            ->filter()
            ->implode(', ');

        return [
            'ip_address'   => $ip,
            'location'     => $place ?: null,
            'coordinates'  => $geo?->latitude && $geo?->longitude
                ? round($geo->latitude, 2).', '.round($geo->longitude, 2)
                : null,
            'network'      => $geo?->asn_organization,
            'is_vpn'       => (bool) ($geo?->is_vpn ?? false),
            'is_datacenter' => (bool) ($geo?->is_datacenter ?? false),
            // desktop / mobile / tablet / unknown
            'device_type'  => ActivityRecorder::deviceType($userAgent),
            'browser'      => ActivityRecorder::browser($userAgent),
            'platform'     => ActivityRecorder::platform($userAgent),
            'user_agent'   => $userAgent,
            'signed_in_at' => et(now(), 'F j, Y \a\t g:ia'),
        ];
    }

    public function handleLogout(Logout $event): void
    {
        if (! $event->user) {
            return; // Logout fires even when there's no user (rare edge case)
        }
        $this->safe(fn () => $this->activity->record(
            ActivityType::LoggedOut,
            $this->request,
            result: 'successful',
        ));

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
        // A failed attempt is the one an auditor looks for, so it carries
        // result: failed rather than being left off the trail.
        $this->safe(fn () => $this->activity->record(
            ActivityType::LoginFailed,
            $this->request,
            subjectType: 'user',
            subjectReference: (string) $event->user->getAuthIdentifier(),
            result: 'failed',
        ));

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
