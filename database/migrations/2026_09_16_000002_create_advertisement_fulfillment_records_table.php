<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Proof that a member received and accepted the advertising they paid for.
 *
 * Three separate facts, never merged into one row:
 *   1. Vaytoven activated the advertisement     (activation, snapshotted here)
 *   2. the member first accessed it              (event = first_access)
 *   3. the member affirmatively accepted it      (event = accepted)
 * plus staff corrections, which are new rows and never edits (event = correction).
 *
 * Identity columns are COPIES, not foreign keys. An evidence row that changes
 * when somebody renames an account, or vanishes when a listing is deleted, is
 * not evidence. Same reasoning as tracking_events.subject_reference.
 *
 * dedupe_key is unique so "first" is enforced by the database: two tabs racing
 * to record a first access produce one row, and a later login cannot replace it.
 *
 * Append-only on MySQL via triggers; SQLite enforces the same rule through the
 * model observer.
 */
return new class extends Migration
{
    private const TRIGGERS = [
        'advertisement_fulfillment_no_update' => 'BEFORE UPDATE',
        'advertisement_fulfillment_no_delete' => 'BEFORE DELETE',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('advertisement_fulfillment_records')) {
            Schema::create('advertisement_fulfillment_records', function (Blueprint $table) {
                $table->id();
                $table->uuid('record_uuid')->unique();

                // first_access | accepted | correction
                // | incentive_presented | incentive_acknowledged | incentive_delivered
                $table->string('event', 24);
                // e.g. first_access:property:40 — NULL for corrections, which may repeat.
                $table->string('dedupe_key', 96)->nullable()->unique();
                // live | backfill:tracking_event
                $table->string('source', 40)->default('live');
                $table->unsignedBigInteger('source_tracking_event_id')->nullable();

                // --- the enrollment incentive (incentive_* events only) -----
                $table->string('incentive_key', 64)->nullable()->index();
                $table->string('incentive_name', 120)->nullable();
                $table->string('incentive_value', 40)->nullable();
                $table->string('incentive_provider', 120)->nullable();
                $table->string('incentive_version', 40)->nullable();
                $table->char('incentive_presentation_hash', 64)->nullable();
                // Delivery is recorded only against evidence of delivery.
                $table->string('delivery_method', 40)->nullable();
                $table->string('delivery_reference', 160)->nullable();

                // --- the advertisement (null for incentive events) ---------
                $table->unsignedBigInteger('property_id')->nullable()->index();
                $table->string('property_reference', 64)->nullable()->index();
                $table->string('advertisement_url', 512)->nullable();
                $table->unsignedBigInteger('advertising_period_id')->nullable()->index();
                $table->unsignedBigInteger('member_service_order_id')->nullable();
                $table->string('order_reference', 40)->nullable();
                $table->string('package', 40)->nullable();
                $table->timestamp('advertisement_activated_at')->nullable();

                // --- the member (authenticated account) ---------------------
                $table->unsignedBigInteger('user_id')->index();
                $table->string('member_number', 40)->nullable();
                $table->string('member_name', 255)->nullable();
                $table->string('member_email', 255)->nullable();
                $table->string('member_role', 32)->nullable();

                // Address on file AT THE TIME of this event. Separate from the
                // IP location below, and compared with it — never merged.
                $table->string('address_line1', 255)->nullable();
                $table->string('address_line2', 255)->nullable();
                $table->string('address_city', 128)->nullable();
                $table->string('address_state', 64)->nullable();
                $table->string('address_postal_code', 20)->nullable();
                $table->char('address_country', 2)->nullable();

                // A POINTER to the first sign-in after activation. That sign-in's
                // IP, device and location live on its own rows and are read
                // from there — never copied onto this one.
                $table->timestamp('first_login_at')->nullable();
                $table->unsignedBigInteger('first_login_event_id')->nullable();
                $table->unsignedBigInteger('first_login_session_id')->nullable();

                // --- acceptance -------------------------------------------
                $table->string('acknowledgement_version', 40)->nullable();
                $table->text('acknowledgement_text')->nullable();
                $table->char('acknowledgement_hash', 64)->nullable();

                // --- correction -------------------------------------------
                $table->unsignedBigInteger('corrects_record_id')->nullable();
                $table->text('correction_note')->nullable();
                $table->unsignedBigInteger('recorded_by_user_id')->nullable();

                // --- request context --------------------------------------
                $table->string('ip_address', 45)->nullable();
                $table->char('country', 2)->nullable();
                $table->string('region', 64)->nullable();
                $table->string('city', 128)->nullable();
                $table->decimal('latitude', 9, 6)->nullable();
                $table->decimal('longitude', 9, 6)->nullable();
                // consistent | nearby | different | unavailable
                $table->string('location_comparison', 16)->nullable();
                $table->decimal('location_distance_miles', 8, 1)->nullable();
                $table->string('location_comparison_reason', 160)->nullable();
                $table->string('device_type', 16)->nullable();
                $table->string('browser', 40)->nullable();
                $table->string('platform', 40)->nullable();
                $table->string('session_id', 40)->nullable();
                $table->string('user_agent', 512)->nullable();
                $table->string('referrer_host', 160)->nullable();
                $table->string('path', 512)->nullable();
                $table->unsignedBigInteger('tracking_event_id')->nullable();

                $table->json('metadata')->nullable();

                // When it happened (server clock) vs when this row was written.
                // Equal for live rows; a backfill keeps the original moment.
                $table->timestamp('occurred_at')->index();
                $table->timestamp('recorded_at');

                $table->char('record_hash', 64);

                $table->index(['user_id', 'property_id']);
            });
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            foreach (self::TRIGGERS as $name => $timing) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$name}");
                DB::unprepared(
                    "CREATE TRIGGER {$name} {$timing} ON advertisement_fulfillment_records FOR EACH ROW "
                    ."SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'advertisement_fulfillment_records is append-only'"
                );
            }
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            foreach (array_keys(self::TRIGGERS) as $name) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$name}");
            }
        }

        Schema::dropIfExists('advertisement_fulfillment_records');
    }
};
