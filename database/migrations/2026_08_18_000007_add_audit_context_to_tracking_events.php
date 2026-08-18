<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The columns the activity log needs and tracking_events did not carry.
 *
 * Device, browser and referrer were only ever derivable by parsing the stored
 * user agent at read time, which meant every filter and every grouping had to
 * re-parse every row. Session was not recorded at all — visitor_id identifies a
 * BROWSER over months, which is the wrong grain for "show me this visit".
 *
 * Result and subject round out the table shown to staff: whether the thing
 * succeeded, and what it happened to.
 *
 * NOTE FOR ANYONE ADDING TO THIS TABLE LATER: it is append-only on MySQL,
 * enforced by a BEFORE UPDATE trigger, and SQLite enforces the same rule
 * through the TrackingEvent observer. A migration that backfills with UPDATE
 * will pass the test suite and fail on deploy. Nothing here backfills.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracking_events', function (Blueprint $table) {
            // A visit, not a browser. visitor_id persists for months; this
            // groups the events of one sitting, which is what "show me the
            // journey" means.
            $table->string('session_id', 40)->nullable()->after('visitor_id');

            $table->string('device_type', 16)->nullable()->after('user_agent');
            $table->string('browser', 40)->nullable()->after('device_type');
            $table->string('platform', 40)->nullable()->after('browser');

            // Where they came from, and where they were.
            $table->string('referrer_host', 160)->nullable()->after('platform');
            $table->string('path', 512)->nullable()->after('referrer_host');

            // What it happened to: a property, an order, an offer. Kept as a
            // loose pair rather than a foreign key because an event may refer
            // to something that is later deleted, and an audit row that
            // disappears with its subject is not an audit row.
            $table->string('subject_type', 40)->nullable()->after('path');
            $table->string('subject_reference', 64)->nullable()->after('subject_type');

            // successful | failed | completed
            $table->string('result', 16)->nullable()->after('subject_reference');

            $table->index('session_id');
            // event_type + occurred_at is already indexed by the geo migration.
            $table->index('subject_reference');
        });
    }

    public function down(): void
    {
        Schema::table('tracking_events', function (Blueprint $table) {
            $table->dropIndex(['session_id']);
            $table->dropIndex(['subject_reference']);
            $table->dropColumn([
                'session_id', 'device_type', 'browser', 'platform',
                'referrer_host', 'path', 'subject_type', 'subject_reference', 'result',
            ]);
        });
    }
};
