<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTrackingEventRequest;
use App\Services\Tracking\TrackingService;
use Illuminate\Http\JsonResponse;

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
        $event = $this->tracking->recordFromRequest(
            $request,
            eventType: $request->string('event_type')->toString(),
            metadata: $request->input('metadata') ?? [],
        );

        return response()->json([
            'event_uuid' => $event->event_uuid,
            'occurred_at' => $event->occurred_at->toIso8601String(),
        ], 201);
    }
}
