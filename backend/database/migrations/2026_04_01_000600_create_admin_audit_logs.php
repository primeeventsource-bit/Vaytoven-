<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')->constrained('users');
            $table->string('action', 80);             // see service ACTIONS for catalog
            $table->string('target_type', 40)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();    // bigint-keyed targets
            $table->uuid('target_uuid')->nullable();                // uuid-keyed targets
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->text('reason')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['admin_user_id', 'created_at']);
            $table->index(['target_type', 'target_id']);
            $table->index(['target_type', 'target_uuid']);
            $table->index('action');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE admin_audit_logs
                ADD CONSTRAINT chk_admin_audit_target_present
                CHECK (target_id IS NOT NULL OR target_uuid IS NOT NULL OR target_type IS NULL)
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
    }
};
