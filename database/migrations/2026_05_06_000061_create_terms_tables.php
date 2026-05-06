<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terms_versions', function (Blueprint $table) {
            $table->id();
            $table->enum('kind', ['tos', 'chargeback', 'privacy', 'member_agreement']);
            $table->string('version_label', 64);
            $table->char('content_hash', 64)->unique(); // SHA-256 of canonical text
            $table->string('content_url', 512);
            $table->timestamp('effective_at');
            $table->timestamp('superseded_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['kind', 'effective_at']);
        });

        Schema::create('terms_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('terms_version_id')->constrained('terms_versions');
            $table->timestamp('accepted_at');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();

            $table->unique(['user_id', 'terms_version_id']);
            $table->index('accepted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terms_acceptances');
        Schema::dropIfExists('terms_versions');
    }
};
