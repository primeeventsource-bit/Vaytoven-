<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_activity', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->ipAddress('ip_address');
            $table->string('country_code', 2)->nullable();
            $table->string('region', 80)->nullable();
            $table->string('city', 80)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_type', 30)->nullable();
            $table->string('os', 60)->nullable();
            $table->string('browser', 60)->nullable();
            $table->string('event', 30); // login_success, login_failed, password_reset, mfa_success, mfa_failed
            $table->boolean('was_suspicious')->default(false);
            $table->json('suspicious_reasons')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index('ip_address');
            $table->index('event');
        });

        Schema::create('ip_location_cache', function (Blueprint $table) {
            $table->ipAddress('ip_address')->primary();
            $table->string('country_code', 2)->nullable();
            $table->string('region', 80)->nullable();
            $table->string('city', 80)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_proxy')->default(false);
            $table->boolean('is_tor')->default(false);
            $table->timestamp('cached_at')->useCurrent();
            $table->timestamp('expires_at');

            $table->index('expires_at');
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 80);     // booking.confirmed, message.new, etc.
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index('created_at');
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 80);
            $table->boolean('email')->default(true);
            $table->boolean('sms')->default(false);
            $table->boolean('push')->default(true);
            $table->boolean('in_app')->default(true);
            $table->timestamps();
            $table->primary(['user_id', 'event_type']);
        });

        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->string('category', 40);   // damage, misrepresentation, cancellation, fraud, other
            $table->text('description');
            $table->string('status', 30)->default('open');
            $table->unsignedInteger('claimed_amount_cents')->nullable();
            $table->unsignedInteger('resolution_amount_cents')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_summary')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('booking_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE disputes ADD CONSTRAINT disputes_status_check
                CHECK (status IN ('open','investigating','awaiting_response','resolved','closed','escalated'))");
        }

        Schema::create('dispute_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('body');
            $table->json('attachments')->nullable();
            $table->boolean('is_admin_note')->default(false);
            $table->timestamps();

            $table->index('dispute_id');
        });

        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 80);
            $table->string('subject_type', 80)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('changes')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['admin_id', 'created_at']);
        });

        Schema::create('content_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->restrictOnDelete();
            $table->string('subject_type', 80);  // property | review | message | user
            $table->unsignedBigInteger('subject_id');
            $table->string('reason', 60);
            $table->text('description')->nullable();
            $table->string('status', 30)->default('pending');
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->text('resolution_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index('status');
        });

        Schema::create('privacy_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);   // export | delete
            $table->string('status', 30)->default('queued');
            $table->text('export_url')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_requests');
        Schema::dropIfExists('content_flags');
        Schema::dropIfExists('admin_audit_logs');
        Schema::dropIfExists('dispute_messages');
        Schema::dropIfExists('disputes');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('ip_location_cache');
        Schema::dropIfExists('login_activity');
    }
};
