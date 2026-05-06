<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GDPR-friendly: when a user is deleted, their login_sessions go with them.
 *
 * The Phase 2F migration left the FK as the Laravel default (RESTRICT) which
 * blocks user deletion when login records exist — broke the existing
 * ProfileController::destroy flow once Phase 8A's TrackAuthEvents started
 * writing login rows during the logout step of account deletion.
 *
 * Forward-only: drops the existing FK, recreates it with cascade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('login_sessions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('login_sessions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users');
        });
    }
};
