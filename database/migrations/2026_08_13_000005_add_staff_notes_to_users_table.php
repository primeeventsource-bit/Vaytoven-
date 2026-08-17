<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Free-text staff notes on a member profile.
 *
 * Kept on the user rather than in a separate notes table on purpose: the need
 * here is "what should the next person picking up this account know?", which
 * is one current answer, not a thread. Anything that needs authorship and
 * chronology already belongs in the activity log, which is append-only and
 * cannot be edited.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('staff_notes')->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('staff_notes');
        });
    }
};
