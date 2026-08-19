<?php

namespace App\Http\Controllers\Api;

use App\Enums\ActivityType;
use App\Http\Controllers\Controller;
use App\Services\Tracking\ActivityRecorder;
use App\Http\Requests\Api\StoreTrackingEventRequest;
use App\Services\Tracking\TrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrackingEventController extends Controller
{
    public function __construct(private readonly TrackingService $tracking)
    {
    }

    /**
     * Anonymous-or-authenticated event ingest endpoint (FR-10.3).
     *
     * Public so the JS SDK on the marketing landing can post page views
     * without requiring a token. Rate-limited at route level (60/min/IP).
     */
    public function store(StoreTrackingEventRequest $request): JsonResponse
    {
        $type = $request->string('event_type')->toString();
        $activity = ActivityType::tryFrom($type);

        // A known activity goes through the recorder, so a client event
        // carries the same session, device, browser and referrer as a
        // server-side one. Without that a journey would lose its shape
        // every time the visitor opened a gallery.
        if ($activity) {
            $event = app(ActivityRecorder::class)->record(
                $activity,
                $request,
                subjectType: $request->input('metadata.subject_type'),
                subjectReference: $request->input('metadata.subject'),
                result: 'successful',
                metadata: $request->input('metadata') ?? [],
                path: $this->reportedPath($request),
            );

            // The recorder swallows its own failures, so a null here means
            // the event was dropped rather than that anything is wrong with
            // the request.
            if (! $event) {
                return response()->json(['accepted' => true], 202);
            }
        } else {
            $event = $this->tracking->recordFromRequest(
                $request,
                eventType: $type,
                metadata: $request->input('metadata') ?? [],
            );
        }

        return response()->json([
            'event_uuid' => $event->event_uuid,
            'occurred_at' => $event->occurred_at->toIso8601String(),
        ], 201);
    }

    /**
     * The page the visitor was actually on.
     *
     * Every browser-reported event posts here, so $request->path() is this
     * endpoint for all of them and the activity log's page column becomes one
     * repeated string. The script sends metadata.path; the Referer header is
     * the fallback when it does not.
     *
     * Both are client-supplied and therefore only as trustworthy as the events
     * themselves, which is why nothing consequential is accepted on this route
     * at all. The path is taken alone — no host, no query string — so a crafted
     * referrer cannot smuggle a link or a tracking parameter into a screen an
     * administrator reads.
     */
    private function reportedPath(Request $request): ?string
    {
        $candidate = $request->input('metadata.path')
            ?: parse_url((string) $request->headers->get('referer'), PHP_URL_PATH);

        if (! is_string($candidate) || $candidate === '') {
            return null;
        }

        return mb_substr(ltrim($candidate, '/'), 0, 512) ?: '/';
    }
}
