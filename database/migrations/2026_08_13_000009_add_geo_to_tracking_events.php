<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
 *
 * TWO THINGS THIS MIGRATION HAS TO WORK AROUND
 *
 * 1. tracking_events is append-only on MySQL, enforced by a BEFORE UPDATE
 *    trigger. The backfill below is an UPDATE, so it is rejected. SQLite
 *    enforces the same rule through the TrackingEvent observer, which the
 *    query builder bypasses — which is why the test suite passed and the
 *    deploy failed. The triggers are dropped for the backfill and restored in
 *    a finally block, and the migration refuses to finish if they are not
 *    back. Only the five geo columns are touched; none of them feed
 *    current_hash, so no row's hash-chain evidence changes meaning.
 *
 * 2. The first attempt got as far as the ALTER TABLE before failing. MySQL DDL
 *    does not roll back, so the columns and the index already exist on any
 *    environment that saw that deploy while the migration is still recorded as
 *    pending. Every step below therefore checks before it acts.
 */
return new class extends Migration
{
    private const TRIGGERS = [
        'tracking_events_no_update' => 'BEFORE UPDATE',
        'tracking_events_no_delete' => 'BEFORE DELETE',
    ];

    public function up(): void
    {
        $this->addColumns();

        if (! Schema::hasIndex('tracking_events', 'tracking_events_event_type_occurred_at_index')) {
            Schema::table('tracking_events', function (Blueprint $table) {
                $table->index(['event_type', 'occurred_at']);
            });
        }

        $mysql = DB::connection()->getDriverName() === 'mysql';

        if ($mysql) {
            $this->dropTriggers();
        }

        try {
            $this->backfillFromMetadata();
        } finally {
            if ($mysql) {
                $this->createTriggers();
                $this->assertTriggersExist();
            }
        }
    }

    public function down(): void
    {
        Schema::table('tracking_events', function (Blueprint $table) {
            if (Schema::hasIndex('tracking_events', 'tracking_events_event_type_occurred_at_index')) {
                $table->dropIndex(['event_type', 'occurred_at']);
            }

            $table->dropColumn(['country', 'region', 'city', 'latitude', 'longitude']);
        });
    }

    /**
     * Added one at a time so a table left half-altered by the earlier failure
     * picks up only what it is missing.
     */
    private function addColumns(): void
    {
        $columns = [
            'country'   => fn (Blueprint $t) => $t->char('country', 2)->nullable()->after('ip_address'),
            'region'    => fn (Blueprint $t) => $t->string('region', 64)->nullable()->after('country'),
            'city'      => fn (Blueprint $t) => $t->string('city', 128)->nullable()->after('region'),
            'latitude'  => fn (Blueprint $t) => $t->decimal('latitude', 9, 6)->nullable()->after('city'),
            'longitude' => fn (Blueprint $t) => $t->decimal('longitude', 9, 6)->nullable()->after('latitude'),
        ];

        foreach ($columns as $name => $define) {
            if (! Schema::hasColumn('tracking_events', $name)) {
                Schema::table('tracking_events', fn (Blueprint $table) => $define($table));
            }
        }
    }

    /**
     * Copies geo out of metadata, where it has been captured all along.
     *
     * Decoded in PHP rather than with a JSON path expression so it behaves the
     * same on SQLite and MySQL, and chunked so a large table does not load
     * itself into memory. Rows that already have a country are skipped, so a
     * re-run after a partial failure resumes rather than rewriting.
     */
    private function backfillFromMetadata(): void
    {
        DB::table('tracking_events')
            ->whereNotNull('metadata')
            ->whereNull('country')
            ->orderBy('id')
            ->select('id', 'metadata')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $meta = json_decode((string) $row->metadata, true);
                    $geo  = $meta['geo'] ?? null;

                    if (! is_array($geo)) {
                        continue;
                    }

                    DB::table('tracking_events')
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

    private function dropTriggers(): void
    {
        foreach (array_keys(self::TRIGGERS) as $name) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$name}");
        }
    }

    private function createTriggers(): void
    {
        foreach (self::TRIGGERS as $name => $timing) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$name}");
            DB::unprepared(
                "CREATE TRIGGER {$name} {$timing} ON tracking_events FOR EACH ROW "
                ."SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'tracking_events is append-only'"
            );
        }
    }

    /**
     * Leaving the append-only guard off would be a far worse outcome than a
     * failed deploy, and it would be silent. Fail loudly instead.
     */
    private function assertTriggersExist(): void
    {
        $present = collect(DB::select('SHOW TRIGGERS LIKE ?', ['tracking_events']))
            ->pluck('Trigger')
            ->all();

        foreach (array_keys(self::TRIGGERS) as $name) {
            if (! in_array($name, $present, true)) {
                throw new RuntimeException(
                    "The append-only trigger {$name} was not restored on tracking_events. "
                    .'Recreate it before allowing writes to continue.'
                );
            }
        }
    }
};
