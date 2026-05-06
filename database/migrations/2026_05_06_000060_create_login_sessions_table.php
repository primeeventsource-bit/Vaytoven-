<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->enum('auth_event', ['login', 'logout', 'failed', 'lockout']);
            $table->enum('surface', ['web', 'app_ios', 'app_android', 'admin']);
            $table->string('session_id', 64)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->char('country', 2)->nullable();
            $table->string('region', 64)->nullable();
            $table->string('city', 128)->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->unsignedInteger('asn')->nullable();
            $table->boolean('is_vpn')->default(false);
            $table->boolean('is_tor')->default(false);
            $table->boolean('is_datacenter')->default(false);
            $table->enum('device_type', ['desktop', 'mobile', 'tablet', 'unknown'])->default('unknown');
            $table->string('os', 64)->nullable();
            $table->string('browser', 64)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->boolean('is_suspicious')->default(false);
            $table->json('suspicious_reasons')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['user_id', 'occurred_at']);
            $table->index('auth_event');
            $table->index('is_suspicious');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_sessions');
    }
};
