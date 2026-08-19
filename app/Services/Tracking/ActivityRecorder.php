<?php

namespace App\Services\Tracking;

use App\Enums\ActivityType;
use App\Models\TrackingEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * Records one meaningful application event.
 *
 * Wraps TrackingService rather than replacing it: that class already owns
 * GeoIP enrichment, metadata filtering and the hash chain. What this adds is
 * the request context an auditor asks for — which visit, on what device, from
 * where, to what, and did it work.
 *
 * Every method swallows its own failures. An audit log that can take the site
 * down is a worse trade than a log with a gap in it: nobody thanks you when
 * checkout 500s because a geo lookup timed out.
 */
class ActivityRecorder
{
    /** Session cookie/key holding the visit id. */
    public const SESSION_KEY = 'vyt_activity_session';

    public function __construct(private readonly TrackingService $tracking)
    {
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        ActivityType $type,
        ?Request $request = null,
        ?string $subjectType = null,
        ?string $subjectReference = null,
        ?string $result = null,
        array $metadata = [],
        ?string $path = null,
    ): ?TrackingEvent {
        $request ??= request();

        try {
            $userAgent = $request?->userAgent();

            return $this->tracking->record(
                eventType:   $type->value,
                actorUserId: $request?->user()?->id,
                visitorId:   $this->visitorId($request),
                ipAddress:   $request?->ip(),
                userAgent:   $userAgent,
                metadata:    $metadata,
                context:     [
                    'session_id'        => $this->sessionId($request),
                    'device_type'       => self::deviceType($userAgent),
                    'browser'           => self::browser($userAgent),
                    'platform'          => self::platform($userAgent),
                    'referrer_host'     => self::referrerHost($request?->headers->get('referer')),
                    // Where the person WAS, not where the request landed.
                    //
                    // Events reported by the browser all post to one ingest
                    // endpoint, so the request path is the same for every one
                    // of them. Recorded as-is, the activity log's page column
                    // read "api/v1/tracking/events" for the entire browsing
                    // group, which tells nobody anything and makes a session
                    // journey unreadable.
                    'path'              => $path ?: $request?->path(),
                    'subject_type'      => $subjectType,
                    'subject_reference' => $subjectReference,
                    'result'            => $result,
                ],
            );
        } catch (Throwable) {
            // Deliberately silent. See the class docblock.
            return null;
        }
    }

    /**
     * The id for THIS visit.
     *
     * Distinct from visitor_id, which identifies a browser for months. A
     * session is what "show me the journey" means, and it is what makes a
     * sequence of events readable as one person's sitting rather than a year
     * of unrelated returns.
     */
    public function sessionId(?Request $request = null): string
    {
        $request ??= request();
        $session = $request?->hasSession() ? $request->session() : null;

        if (! $session) {
            return 'SES-'.strtoupper(Str::random(6));
        }

        if (! $session->has(self::SESSION_KEY)) {
            $session->put(self::SESSION_KEY, 'SES-'.strtoupper(Str::random(6)));
        }

        return (string) $session->get(self::SESSION_KEY);
    }

    private function visitorId(?Request $request): ?string
    {
        return $request?->attributes->get('vyt_visitor_id')
            ?: $request?->cookie('vyt_visitor');
    }

    /**
     * Coarse device class from a user agent.
     *
     * Three buckets, because that is the granularity anyone actually acts on.
     * A precise device string is both less useful and more identifying — a
     * rare user agent is close to a name.
     */
    public static function deviceType(?string $userAgent): string
    {
        $ua = strtolower((string) $userAgent);

        if ($ua === '') {
            return 'unknown';
        }

        // Order matters: an iPad reports "mobile" too, so tablets are checked
        // first or every tablet is filed as a phone.
        if (str_contains($ua, 'ipad') || str_contains($ua, 'tablet')
            || (str_contains($ua, 'android') && ! str_contains($ua, 'mobile'))) {
            return 'tablet';
        }

        if (str_contains($ua, 'mobi') || str_contains($ua, 'iphone') || str_contains($ua, 'android')) {
            return 'mobile';
        }

        return 'desktop';
    }

    /**
     * Browser family.
     *
     * Order matters here too: Edge and Chrome both claim "chrome", and Chrome
     * and Safari both claim "safari". Checking the most specific first is the
     * difference between a useful column and one that says "Chrome" for
     * everything.
     */
    public static function browser(?string $userAgent): string
    {
        $ua = strtolower((string) $userAgent);

        return match (true) {
            $ua === ''                    => 'unknown',
            str_contains($ua, 'edg/')     => 'Edge',
            str_contains($ua, 'opr/')     => 'Opera',
            str_contains($ua, 'samsungbrowser') => 'Samsung Internet',
            str_contains($ua, 'firefox')  => 'Firefox',
            str_contains($ua, 'chrome')   => 'Chrome',
            str_contains($ua, 'safari')   => 'Safari',
            str_contains($ua, 'bot') || str_contains($ua, 'crawler') || str_contains($ua, 'spider') => 'Bot',
            default                       => 'Other',
        };
    }

    public static function platform(?string $userAgent): string
    {
        $ua = strtolower((string) $userAgent);

        return match (true) {
            $ua === ''                     => 'unknown',
            str_contains($ua, 'iphone') || str_contains($ua, 'ipad') => 'iOS',
            str_contains($ua, 'android')   => 'Android',
            str_contains($ua, 'mac os')    => 'macOS',
            str_contains($ua, 'windows')   => 'Windows',
            str_contains($ua, 'linux')     => 'Linux',
            default                        => 'Other',
        };
    }

    /**
     * Where the visit came from, as a host.
     *
     * The host only — a full referrer URL routinely carries the visitor's
     * search terms, and a search query is often personal in a way the fact of
     * arriving from Google is not.
     */
    public static function referrerHost(?string $referrer): ?string
    {
        if (! $referrer) {
            return null;
        }

        $host = parse_url($referrer, PHP_URL_HOST);

        if (! $host) {
            return null;
        }

        $host = strtolower(preg_replace('/^www\./', '', $host));

        return $host === parse_url((string) config('app.url'), PHP_URL_HOST) ? 'direct' : $host;
    }
}
