<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The enquiry form no longer asks which club a property belongs to, or how
 * many points the owner holds.
 *
 * Vaytoven advertises vacation properties. Collecting a club name and a points
 * balance framed the service as a points-club rental programme, which is not
 * what is sold. The two columns were NOT NULL, so the form could not simply
 * stop sending them.
 *
 * The columns are kept rather than dropped. Existing enquiries hold real
 * answers people gave, and dropping a column to tidy up a form throws away
 * their submission. Nothing new is written to either.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members_enquiries', function (Blueprint $table) {
            $table->string('club', 80)->nullable()->change();
            $table->string('points', 60)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Rows written after this migration have nulls, so they must be given a
        // value before the column can be NOT NULL again.
        \Illuminate\Support\Facades\DB::table('members_enquiries')->whereNull('club')->update(['club' => '']);
        \Illuminate\Support\Facades\DB::table('members_enquiries')->whereNull('points')->update(['points' => '']);

        Schema::table('members_enquiries', function (Blueprint $table) {
            $table->string('club', 80)->nullable(false)->change();
            $table->string('points', 60)->nullable(false)->change();
        });
    }
};
