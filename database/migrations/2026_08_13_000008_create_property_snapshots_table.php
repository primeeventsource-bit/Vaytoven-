<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Point-in-time copies of what a listing actually said.
 *
 * A listing is edited continuously — rates change, descriptions get rewritten,
 * photos are swapped. Six months later a member disputes the charge and the
 * only thing the system can show is the CURRENT version, which may share
 * nothing with what ran during the period they paid for. "Here is the ad we
 * published for you" is only evidence if it is the ad that was actually
 * published then.
 *
 * Rows are written and never updated. content_hash makes that demonstrable:
 * if the stored JSON were altered afterwards, the hash recorded at capture
 * time would no longer match it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_snapshots', function (Blueprint $table) {
            $table->id();

            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();

            // Why this snapshot exists — activation, a material edit, or a
            // member of staff taking one deliberately.
            $table->string('reason', 32);

            // The listing as published, as JSON. Stored rather than referenced
            // so it survives the property being edited, or deleted.
            $table->json('content');

            // SHA-256 of the canonical JSON at capture time.
            $table->char('content_hash', 64)->index();

            $table->foreignId('captured_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('captured_at');

            // No updated_at: these are never modified.
            $table->timestamp('created_at')->nullable();

            $table->index(['property_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_snapshots');
    }
};
