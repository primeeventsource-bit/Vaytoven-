<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which advertised week an offer is for.
 *
 * Offers carried a property and a pair of dates the visitor typed, and nothing
 * tying them to the time actually being advertised. So a member with three
 * weeks listed received offers they had to match up by eye, and nothing stopped
 * an offer arriving for dates that were never on sale.
 *
 * Nullable: offers already in the system predate this, and an inquiry that is a
 * question rather than a bid for specific dates legitimately has no week.
 *
 * nullOnDelete, not cascade. Removing a week from the calendar must not delete
 * the offers somebody made against it - the offer still happened, and the
 * member may still be corresponding about it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_offers', function (Blueprint $table) {
            $table->foreignId('availability_week_id')->nullable()->after('property_id')
                ->constrained('property_availability_weeks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('member_offers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('availability_week_id');
        });
    }
};
