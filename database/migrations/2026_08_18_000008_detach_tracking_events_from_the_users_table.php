<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the foreign key from tracking_events.actor_user_id to users.
 *
 * Deleting an account became impossible the moment auth events started being
 * recorded: logging out writes an activity row referencing the user, and the
 * restricting foreign key then refuses the delete. A person asking to be
 * removed should not be blocked by the log that noticed them leaving.
 *
 * ON DELETE SET NULL is the usual answer and is NOT available here.
 * tracking_events is append-only, enforced on MySQL by a BEFORE UPDATE
 * trigger, and SET NULL is an UPDATE of the child row — the database would
 * refuse its own cascade and the delete would fail anyway, with a far more
 * confusing error.
 *
 * So the column stays, indexed, without the constraint. That is the same rule
 * already applied to subject_type/subject_reference on this table and for the
 * same reason: an audit row that disappears with its subject is not an audit
 * row. A historical actor id that no longer resolves is a true statement about
 * the past, and the row keeps its IP, session, device and timestamp regardless.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracking_events', function (Blueprint $table) {
            $table->dropForeign(['actor_user_id']);
        });
    }

    public function down(): void
    {
        // Only restorable if every recorded actor still exists. Rows written
        // for since-deleted accounts would refuse the constraint, which is the
        // situation this migration exists to allow.
        Schema::table('tracking_events', function (Blueprint $table) {
            $table->foreign('actor_user_id')->references('id')->on('users');
        });
    }
};
