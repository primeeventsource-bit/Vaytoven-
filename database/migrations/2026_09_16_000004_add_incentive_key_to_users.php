<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which enrollment thank-you offer staff assigned to this client.
 *
 * Null means "the default chosen on Marketing → Incentive offers". Once an
 * offer has been presented, the audit record — not this column — says what
 * the client saw; the assignment is locked from then on.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'incentive_key')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('incentive_key', 64)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('incentive_key');
        });
    }
};
