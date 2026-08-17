<?php

namespace App\Services\Tracking;

use App\Enums\Surface;
use App\Models\PpcVisitor;
use App\Models\TrackingEvent;
use App\Services\GeoIp\GeoIpService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Records tracking events (FR-10.1, FR-10.2) with hash chain integrity and
 * first-touch attribution capture for paid traffic (FR-10.4).
 *
 * Hash chain enforcement happens inside the TrackingEvent model's `creating`
 * hook — this service handles request-side concerns:
 *   1. PII filtering (no card data, no full session tokens)
 *   2. Visitor first-touch capture into ppc_visitors
 *   3. IP / user-agent extraction from the request
 *
 * IP geolocation enrichment is Phase 9 (MaxMind/ipinfo); this service stores
 * the raw IP for now and a follow-up job populates geo fields.
 */
class TrackingService
{
    public function __construct(private readonly GeoIpService $geoIp)
    {
    }

    public function record(
        string $eventType,
        ?int $actorUserId = null,
        ?string $visitorId = null,
        Surface $surface = Surface::Web,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?array $metadata = null,
    ): TrackingEvent {
        // Strip anything from metadata that we don't want stored.
        $metadata = $this->filterMetadata($metadata ?? []);

        // GeoIP enrichment (FR-10.5). Lookup failures are silent — the
        // GeoIpService contract guarantees no exceptions bubble up.
        $geoColumns = [];

        if ($ipAddress) {
            $geo = $this->geoIp->lookup($ipAddress);

            if ($geo->isResolved() || $geo->is_vpn || $geo->is_tor || $geo->is_datacenter) {
                // Kept in metadata.geo, where it has always lived and where
                // the fraud signals (vpn/tor/datacenter/asn) belong.
                $metadata['geo'] = $geo->toArray();

                // ...and promoted to columns. The engagement map aggregates
                // clicks by city over a date range, and a JSON path is neither
                // indexable nor written the same way across SQLite and MySQL —
                // the alternative was pulling every row into PHP to group it.
                $geoColumns = [
                    'country'   => $geo->country,
                    'region'    => $geo->region,
                    'city'      => $geo->city,
                    'latitude'  => $geo->latitude,
                    'longitude' => $geo->longitude,
                ];
            }
        }

        return TrackingEvent::create([
            'event_type' => $eventType,
            'actor_user_id' => $actorUserId,
            'visitor_id' => $visitorId,
            'surface' => $surface->value,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent ? mb_substr($userAgent, 0, 512) : null,
            'metadata' => $metadata,
            // event_uuid, occurred_at, parent_hash, current_hash are filled by
            // the model's creating hook (Phase 2E).
        ] + $geoColumns);
    }

    /**
     * Convenience wrapper: extract everything from a Request, then record.
     *
     * Also captures first-touch attribution into ppc_visitors when the visitor
     * arrives with UTM/click-id parameters (FR-10.4). Subsequent visits with
     * the same visitor_id are no-ops thanks to firstOrCreate.
     */
    public function recordFromRequest(
        Request $request,
        string $eventType,
        ?array $metadata = null,
    ): TrackingEvent {
        $surface = $request->attributes->get('vaytoven_surface') ?? Surface::Web;
        if (! $surface instanceof Surface) {
            $surface = Surface::tryFrom((string) $surface) ?? Surface::Web;
        }

        $visitorId = $request->cookie('vyt_vid') ?? $request->input('visitor_id');

        // First-touch attribution capture.
        if ($visitorId) {
            $this->capturePpcFirstTouch($request, $visitorId);
        }

        // The route is anonymous (no auth:sanctum middleware) so $request->user()
        // is null even with a Bearer token. Try the sanctum guard explicitly so
        // an authenticated traveler's events still link back to their user_id.
        $actorUserId = $request->user()?->id ?? Auth::guard('sanctum')->user()?->id;

        return $this->record(
            eventType: $eventType,
            actorUserId: $actorUserId,
            visitorId: $visitorId,
            surface: $surface,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            metadata: $metadata,
        );
    }

    private function capturePpcFirstTouch(Request $request, string $visitorId): void
    {
        $utm = [
            'utm_source' => $request->input('utm_source'),
            'utm_medium' => $request->input('utm_medium'),
            'utm_campaign' => $request->input('utm_campaign'),
            'utm_term' => $request->input('utm_term'),
            'utm_content' => $request->input('utm_content'),
            'gclid' => $request->input('gclid'),
            'fbclid' => $request->input('fbclid'),
        ];

        // Skip if there's nothing attribution-relevant — saves a row write
        // for every page view from organic traffic.
        if (! array_filter($utm, fn ($v) => ! empty($v))) {
            return;
        }

        PpcVisitor::firstOrCreate(
            ['visitor_id' => $visitorId],
            array_merge($utm, [
                'first_seen_at' => Carbon::now(),
                'landing_url' => $request->fullUrl(),
                'referrer' => $request->header('Referer'),
            ])
        );
    }

    /**
     * Strip metadata keys we never want persisted (PII, card data, secrets).
     * The blocklist is conservative — anything not strictly needed for evidence.
     */
    private function filterMetadata(array $metadata): array
    {
        $blocked = [
            'card', 'card_number', 'cvc', 'cvv', 'pan',
            'password', 'token', 'session_id', 'api_key',
            'authorization', 'cookie',
        ];

        return collect($metadata)
            ->reject(fn ($_, $k) => in_array(strtolower((string) $k), $blocked, true))
            ->all();
    }
}
