<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->string('event_type', 64);
            $table->foreignId('actor_user_id')->nullable()->constrained('users');
            $table->string('visitor_id', 36)->nullable();
            $table->enum('surface', ['web', 'app_ios', 'app_android', 'admin']);
            // ip stored as string for portability across MySQL VARBINARY and SQLite TEXT.
            // PII redaction is responsibility of the writer (TrackingService — Phase 6).
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->json('metadata')->nullable();
            // Hash chain (FR-10.2): each row's current_hash = sha256(parent || event_uuid || type || actor_id || metadata).
            $table->char('parent_hash', 64)->nullable();
            $table->char('current_hash', 64);
            $table->timestamp('occurred_at');

            $table->index('event_type');
            $table->index('actor_user_id');
            $table->index('visitor_id');
            $table->index('occurred_at');
        });

        // MySQL-only: append-only enforcement at the DB level.
        // SQLite (test) enforces via the App\Models\TrackingEvent observer.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER tracking_events_no_update
                BEFORE UPDATE ON tracking_events
                FOR EACH ROW
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'tracking_events is append-only';
            SQL);
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER tracking_events_no_delete
                BEFORE DELETE ON tracking_events
                FOR EACH ROW
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'tracking_events is append-only';
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS tracking_events_no_update');
            DB::unprepared('DROP TRIGGER IF EXISTS tracking_events_no_delete');
        }
        Schema::dropIfExists('tracking_events');
    }
};
