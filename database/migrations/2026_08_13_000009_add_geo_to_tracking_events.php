<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resolved location on click events.
 *
 * property_views already carries country/region/city/lat/lng, which is why the
 * existing maps work. tracking_events — where the CLICKS live — only ever had
 * a raw IP, so "where is my ad being clicked from" could not be answered at
 * all.
 *
 * Coordinates are stored at the precision the GeoIP database gives, which is
 * city-level and approximate by nature. What the member is shown is rounded
 * further; see MemberEngagementMap. The raw IP stays out of anything
 * member-facing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracking_events', function (Blueprint $table) {
            $table->char('country', 2)->nullable()->after('ip_address');
            $table->string('region', 64)->nullable()->after('country');
            $table->string('city', 128)->nullable()->after('region');
            $table->decimal('latitude', 9, 6)->nullable()->after('city');
            $table->decimal('longitude', 9, 6)->nullable()->after('latitude');

            $table->index(['event_type', 'occurred_at']);
        });

        // Backfill from metadata.geo, where this has been captured all along.
        // Done in PHP rather than a JSON path expression so it behaves the
        // same on SQLite and MySQL, and chunked so a large table does not load
        // itself into memory.
        \Illuminate\Support\Facades\DB::table('tracking_events')
            ->whereNotNull('metadata')
            ->orderBy('id')
            ->select('id', 'metadata')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $meta = json_decode((string) $row->metadata, true);
                    $geo  = $meta['geo'] ?? null;

                    if (! is_array($geo)) {
                        continue;
                    }

                    \Illuminate\Support\Facades\DB::table('tracking_events')
                        ->where('id', $row->id)
                        ->update([
                            'country'   => $geo['country']   ?? null,
                            'region'    => $geo['region']    ?? null,
                            'city'      => $geo['city']      ?? null,
                            'latitude'  => $geo['latitude']  ?? null,
                            'longitude' => $geo['longitude'] ?? null,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('tracking_events', function (Blueprint $table) {
            $table->dropIndex(['event_type', 'occurred_at']);
            $table->dropColumn(['country', 'region', 'city', 'latitude', 'longitude']);
        });
    }
};
