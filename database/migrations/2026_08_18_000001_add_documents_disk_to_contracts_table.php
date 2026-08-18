<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which disk a contract's signed PDF and certificate were written to.
 *
 * Both paths were written to, and read back from, a hardcoded `local` disk.
 * On Laravel Cloud that disk lives inside the container, so every signed
 * contract on the environment serving the public site was being lost at the
 * next deploy — silently, since the row kept its path and the download only
 * fails when somebody actually needs the document.
 *
 * Storing the disk beside the path is the same rule member_documents already
 * follows: a path with no record of where it was written becomes unresolvable
 * the moment the default moves. Existing rows are stamped `local` because that
 * is where they were genuinely written; whether the file survived is a
 * separate question, and fileExists() is what answers it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->string('documents_disk', 32)->nullable()->after('certificate_pdf_path');
        });

        DB::table('contracts')
            ->whereNotNull('signed_pdf_path')
            ->update(['documents_disk' => 'local']);
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('documents_disk');
        });
    }
};
