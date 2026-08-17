<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\TrackingEvent;
use App\Services\Analytics\ListingAnalytics;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Platform-wide listing activity: what every owner's advertisement is doing.
 *
 * Hosts and members each see their own listings on their dashboard. This is
 * the same panel — views, geography, the visitor map — across every listing on
 * the platform, plus the click stream, which nothing surfaced before beyond a
 * bare 24-hour count.
 */
class ActivityController extends Controller
{
    public function __construct(private readonly ListingAnalytics $analytics)
    {
    }

    public function index(Request $request): View
    {
        $ownerId = $request->integer('owner') ?: null;

        $listings = Property::query()
            ->with('host:id,name,email')
            ->when($ownerId, fn ($q) => $q->where('host_id', $ownerId))
            ->orderBy('title')
            ->get();

        return view('admin.activity.index', [
            'listings' => $listings,
            'owners'   => Property::query()
                ->with('host:id,name,email')
                ->get()
                ->pluck('host')
                ->filter()
                ->unique('id')
                ->sortBy('name')
                ->values(),
            'ownerId' => $ownerId,
            'clicks'  => $this->clickBreakdown(),
        ] + $this->analytics->payload($listings));
    }

    /**
     * The click stream, grouped by CTA.
     *
     * Every tracked control on the site carries data-track-cta, and
     * vyt-track.js posts a cta_click event naming it. Until now that only ever
     * produced a single "events in 24h" number on the dashboard, which tells
     * you the SDK is alive and nothing else.
     *
     * @return array{by_cta: array, by_audience: array, total_30d: int, total_7d: int}
     */
    private function clickBreakdown(): array
    {
        $cutoff30 = now()->subDays(30);
        $cutoff7  = now()->subDays(7);

        $base = fn () => TrackingEvent::query()
            ->where('event_type', 'cta_click')
            ->where('occurred_at', '>=', $cutoff30);

        // metadata is JSON; the CTA name and audience live inside it. Pulled
        // in PHP rather than with a driver-specific JSON path so this behaves
        // the same on SQLite in tests and MySQL in production.
        $rows = $base()->select('metadata', 'occurred_at')->limit(20000)->get();

        $byCta = [];
        $byAudience = [];
        $total7d = 0;

        foreach ($rows as $row) {
            $meta = is_array($row->metadata) ? $row->metadata : (json_decode((string) $row->metadata, true) ?: []);

            $cta = $meta['cta'] ?? null;
            if ($cta) {
                $byCta[$cta] = ($byCta[$cta] ?? 0) + 1;
            }

            $audience = $meta['audience'] ?? 'unknown';
            $byAudience[$audience] = ($byAudience[$audience] ?? 0) + 1;

            if ($row->occurred_at && $row->occurred_at->greaterThanOrEqualTo($cutoff7)) {
                $total7d++;
            }
        }

        arsort($byCta);
        arsort($byAudience);

        return [
            'by_cta'      => array_slice($byCta, 0, 25, true),
            'by_audience' => $byAudience,
            'total_30d'   => $rows->count(),
            'total_7d'    => $total7d,
        ];
    }
}
