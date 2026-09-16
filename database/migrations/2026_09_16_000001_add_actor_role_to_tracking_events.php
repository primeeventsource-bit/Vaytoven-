<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The role the actor held WHEN the event was written.
 *
 * The activity log sorted rows into tabs by event type alone, and the admin
 * listing tools write member.* types — so a super admin publishing a listing
 * was filed under "Members" and the Admin tab read zero. Who did something is
 * a fact about the authenticated account, not about the event's name.
 *
 * A column rather than a join to users.role, because a role can change later
 * and an audit row must describe the moment it was written.
 *
 * DDL only. tracking_events is append-only (MySQL trigger), so nothing here
 * backfills. Rows written before this column existed are classified at read
 * time from the account's current role — see ActivityLogQuery — which changes
 * no stored data.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tracking_events', 'actor_role')) {
            Schema::table('tracking_events', function (Blueprint $table) {
                $table->string('actor_role', 32)->nullable()->after('actor_user_id');
            });
        }

        if (! Schema::hasIndex('tracking_events', 'tracking_events_actor_role_index')) {
            Schema::table('tracking_events', function (Blueprint $table) {
                $table->index('actor_role');
            });
        }
    }

    public function down(): void
    {
        Schema::table('tracking_events', function (Blueprint $table) {
            $table->dropIndex(['actor_role']);
            $table->dropColumn('actor_role');
        });
    }
};
