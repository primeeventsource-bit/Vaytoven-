<?php

namespace App\Services\Tracking;

use App\Enums\ActivityType;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Services\GeoIp\GeoIpService;
use App\Support\Location\AddressOnFile;
use App\Support\Location\LocationComparison;
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
        // Who did it, when the caller knows better than the request does —
        // e.g. the Login event, which fires before every guard has caught up.
        ?User $actor = null,
        // For an event the member performed somewhere other than this request
        // — a DocuSign signature reported by webhook. The webhook's own IP and
        // session are DocuSign's and must never be recorded as the member's.
        // Keys: ip, user_agent, source.
        ?array $observedElsewhere = null,
    ): ?TrackingEvent {
        $request ??= request();

        try {
            $actor ??= $request?->user();

            $elsewhere = $observedElsewhere !== null;
            $userAgent = $elsewhere ? ($observedElsewhere['user_agent'] ?? null) : $request?->userAgent();
            $ip        = $elsewhere ? ($observedElsewhere['ip'] ?? null) : $request?->ip();

            if ($elsewhere) {
                $metadata['observed_by'] = $observedElsewhere['source'] ?? 'external';
            }

            if ($actor && ! $actor->isStaff() && in_array($type->value, ActivityType::deviceEvidence(), true)) {
                $metadata += $this->memberEvidence($actor, $ip);
            }

            return $this->tracking->record(
                eventType:   $type->value,
                actorUserId: $actor?->id,
                visitorId:   $elsewhere ? null : $this->visitorId($request),
                ipAddress:   $ip,
                userAgent:   $userAgent,
                metadata:    $metadata,
                context:     [
                    // The role held at the moment of the event. This, not the
                    // event type, decides Member vs Admin in the activity log.
                    'actor_role'        => $actor?->role?->value,
                    'session_id'        => $elsewhere ? null : $this->sessionId($request),
                    'device_type'       => self::deviceType($userAgent),
                    'browser'           => self::browser($userAgent),
                    'platform'          => self::platform($userAgent),
                    'referrer_host'     => $elsewhere ? null : self::referrerHost($request?->headers->get('referer')),
                    // Where the person WAS, not where the request landed.
                    //
                    // Events reported by the browser all post to one ingest
                    // endpoint, so the request path is the same for every one
                    // of them. Recorded as-is, the activity log's page column
                    // read "api/v1/tracking/events" for the entire browsing
                    // group, which tells nobody anything and makes a session
                    // journey unreadable.
                    'path'              => $path ?: ($elsewhere ? null : $request?->path()),
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
     * Who the member is, the address they have on file right now, and how that
     * address compares with THIS event's approximate IP location.
     *
     * Snapshotted into the event's metadata (which the hash chain covers), so
     * a later address change cannot rewrite what the comparison said at the
     * time. Each event is compared against its own IP — never a copy of an
     * earlier login's.
     *
     * @return array<string, mixed>
     */
    private function memberEvidence(User $member, ?string $ip): array
    {
        $address = AddressOnFile::fromUser($member);
        $geo     = $ip ? app(GeoIpService::class)->lookup($ip) : null;

        return [
            'member' => [
                'id'            => $member->id,
                'member_number' => $member->member_id,
                'name'          => $member->name,
                'email'         => $member->email,
            ],
            'address_on_file'     => $address->isKnown() || filled($address->line1) ? $address->toArray() : null,
            'location_comparison' => LocationComparison::compare(
                $address, $geo?->city, $geo?->region, $geo?->country, $geo?->latitude, $geo?->longitude,
            )->toArray(),
        ];
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

        // The families the evidence record names: Chrome, Safari, Edge,
        // Firefox, Samsung Internet, Other. The iOS builds of Chrome, Firefox
        // and Edge carry "Safari" and not their own name, so they are matched
        // by their iOS tokens first or every iPhone reads as Safari.
        return match (true) {
            $ua === ''                    => 'Other',
            str_contains($ua, 'bot') || str_contains($ua, 'crawler') || str_contains($ua, 'spider') => 'Bot',
            str_contains($ua, 'edg/') || str_contains($ua, 'edgios/') || str_contains($ua, 'edga/') => 'Edge',
            str_contains($ua, 'opr/') || str_contains($ua, 'opera') => 'Other',
            str_contains($ua, 'samsungbrowser') => 'Samsung Internet',
            str_contains($ua, 'firefox') || str_contains($ua, 'fxios/') => 'Firefox',
            str_contains($ua, 'crios/')   => 'Chrome',
            str_contains($ua, 'chrome')   => 'Chrome',
            str_contains($ua, 'safari')   => 'Safari',
            default                       => 'Other',
        };
    }

    /** Windows, macOS, iOS, Android, Linux or Other. */
    public static function platform(?string $userAgent): string
    {
        $ua = strtolower((string) $userAgent);

        return match (true) {
            $ua === ''                     => 'Other',
            str_contains($ua, 'iphone') || str_contains($ua, 'ipad') || str_contains($ua, 'ipod') => 'iOS',
            str_contains($ua, 'android')   => 'Android',
            str_contains($ua, 'mac os') || str_contains($ua, 'macintosh') => 'macOS',
            str_contains($ua, 'windows')   => 'Windows',
            str_contains($ua, 'cros')      => 'Other',
            str_contains($ua, 'linux')     => 'Linux',
            default                        => 'Other',
        };
    }

    /**
     * A plain-language device, for staff reading evidence: "iPhone",
     * "Android phone", "Windows PC". Coarse on purpose — never a model number.
     */
    public static function deviceHint(?string $userAgent): ?string
    {
        $ua = strtolower((string) $userAgent);

        return match (true) {
            $ua === ''                    => null,
            str_contains($ua, 'iphone')   => 'iPhone',
            str_contains($ua, 'ipad')     => 'iPad',
            str_contains($ua, 'android')  => str_contains($ua, 'mobile') ? 'Android phone' : 'Android tablet',
            str_contains($ua, 'cros')     => 'Chromebook',
            str_contains($ua, 'windows')  => 'Windows PC',
            str_contains($ua, 'macintosh') || str_contains($ua, 'mac os') => 'Mac',
            str_contains($ua, 'linux')    => 'Linux PC',
            default                       => null,
        };
    }

    /** Display form of the stored device category: Desktop, Mobile, Tablet, Unknown. */
    public static function deviceLabel(?string $deviceType): string
    {
        return in_array($deviceType, ['desktop', 'mobile', 'tablet'], true) ? ucfirst($deviceType) : 'Unknown';
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
