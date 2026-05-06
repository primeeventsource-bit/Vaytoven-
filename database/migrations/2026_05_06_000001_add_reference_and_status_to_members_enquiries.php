<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members_enquiries', function (Blueprint $table) {
            // Public-facing handle the prospect can quote when they reply to
            // the confirmation email or call in. Short, all-caps, easy to read
            // over the phone — not a UUID. Format: VYT-XXXXXXXX (8 base32 chars).
            // Nullable in schema; controller always sets it. The unique index
            // gives the real integrity guarantee.
            $table->string('reference', 20)->nullable()->after('id');

            // Lifecycle so ops can triage. New rows default to 'new'; ops moves
            // to contacted → converted/declined as the conversation progresses.
            $table->string('status', 20)->default('new')->after('contact_window');
        });

        // Backfill any pre-existing rows so the unique index can be applied
        // without surprise. On a fresh test DB this is a no-op.
        DB::table('members_enquiries')->whereNull('reference')->orderBy('id')->each(function ($row) {
            DB::table('members_enquiries')
                ->where('id', $row->id)
                ->update(['reference' => 'VYT-'.strtoupper(Str::random(8))]);
        });

        Schema::table('members_enquiries', function (Blueprint $table) {
            $table->unique('reference');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('members_enquiries', function (Blueprint $table) {
            $table->dropUnique(['reference']);
            $table->dropIndex(['status']);
            $table->dropColumn(['reference', 'status']);
        });
    }
};
