<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscribers', function (Blueprint $table) {
            $table->id();

            // Unique on email so re-submitting the same address is a no-op.
            // Phone is collected but not unique (households share numbers).
            $table->string('full_name', 160);
            $table->string('email', 191)->unique();
            $table->string('phone', 40)->nullable();

            // Lifecycle. 'active' = receiving newsletter, 'unsubscribed' =
            // user opted out. Soft state instead of deletion so we can honour
            // CCPA/GDPR delete requests separately from suppression.
            $table->string('status', 20)->default('active');
            $table->timestamp('unsubscribed_at')->nullable();

            // Capture context for spam triage and audit.
            $table->string('source_url', 500)->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscribers');
    }
};
