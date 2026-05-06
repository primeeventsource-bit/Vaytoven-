<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoginHistoryController extends Controller
{
    /**
     * GET /api/v1/me/login-history — current user's own auth records (FR-10.7).
     * Paginated, latest first.
     */
    public function mine(Request $request): JsonResponse
    {
        $rows = $request->user()->loginSessions()
            ->orderByDesc('occurred_at')
            ->paginate(50);

        return response()->json([
            'data' => $rows->items(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'total' => $rows->total(),
            ],
        ]);
    }

    /**
     * GET /api/v1/me/activity-map — geo-coalesced points for the user's
     * map view. Each point: { lat, lng, count, last_seen_at }.
     */
    public function activityMap(Request $request): JsonResponse
    {
        $sessions = $request->user()->loginSessions()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderByDesc('occurred_at')
            ->limit(500)
            ->get();

        $points = [];
        foreach ($sessions as $row) {
            // Cluster by 0.5° lat/lng (~50 km bucket) so the map isn't 500 dots.
            $key = round((float) $row->latitude, 1).':'.round((float) $row->longitude, 1);
            if (! isset($points[$key])) {
                $points[$key] = [
                    'lat' => (float) $row->latitude,
                    'lng' => (float) $row->longitude,
                    'count' => 0,
                    'last_seen_at' => $row->occurred_at?->toIso8601String(),
                    'country' => $row->country,
                    'city' => $row->city,
                ];
            }
            $points[$key]['count']++;
        }

        return response()->json([
            'points' => array_values($points),
        ]);
    }
}
