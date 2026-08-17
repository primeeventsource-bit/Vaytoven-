<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Files attached to a member: agreements, invoices, receipts, property papers.
 *
 * Keeps the complete member file with the member instead of scattered across
 * inboxes and desktops. Every row records who uploaded it and when, because a
 * document with no provenance is not much use in a dispute.
 *
 * The disk is stored alongside the path. Storage configuration changes over
 * time, and a path with no record of which disk it was written to becomes
 * unresolvable the moment the default moves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('category', 40);          // agreement, invoice, receipt, ...
            $table->string('title', 200);

            $table->string('disk', 32);
            $table->string('path', 512);

            $table->string('original_name', 255);
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('size_bytes');

            // Lets the stored file be shown not to have changed since upload,
            // the same guarantee the ad snapshots carry.
            $table->char('sha256', 64);

            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_documents');
    }
};
