<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `proposed_guests` was NOT NULL with a default of 2, which was right when the
 * only offers were outbound proposals Vaytoven authored and always sized.
 *
 * A visitor submitting an inquiry may not name a party size at all, and
 * defaulting them to "2 guests" would put a number in front of the listing
 * member that the visitor never said. Widening to nullable so "not stated"
 * survives as itself.
 *
 * The sibling columns proposed_check_in / proposed_check_out were already
 * widened for the same reason; this one was missed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_offers', function (Blueprint $table) {
            $table->unsignedTinyInteger('proposed_guests')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('member_offers', function (Blueprint $table) {
            $table->unsignedTinyInteger('proposed_guests')->default(2)->nullable(false)->change();
        });
    }
};
