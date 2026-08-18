<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A document may belong to a member, or to one of that member's properties.
 *
 * One table rather than two. A parallel property_documents table would
 * duplicate the storage guard, the hashing, the audit trail and the
 * missing-file handling — and then drift from them, because nobody remembers
 * to fix a bug twice. The only real difference between the two cases is
 * whether the document is about the member or about one property they own.
 *
 * Nullable on purpose: a member agreement belongs to the member, not to any
 * one property, and forcing a property onto it would be a lie about what the
 * document is.
 *
 * nullOnDelete, not cascade. Deleting a property must not silently destroy the
 * advertising agreement that was signed for it — the document outlives the
 * listing and reverts to being a member-level record, which is what it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_documents', function (Blueprint $table) {
            $table->foreignId('property_id')->nullable()->after('user_id')
                ->constrained('properties')->nullOnDelete();

            $table->index(['property_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::table('member_documents', function (Blueprint $table) {
            $table->dropIndex(['property_id', 'category']);
            $table->dropConstrainedForeignId('property_id');
        });
    }
};
