<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The submission form covers a single property OR a Vacation Club resort, and
 * those are not the same thing to whoever picks the enquiry up.
 *
 * A resort submission needs the resort's name, which club or developer it sits
 * under, and what the owner actually holds (a fixed week, floating weeks, or a
 * points allocation) — none of which a house in Puglia has.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hosting_enquiries', function (Blueprint $table) {
            // 'property' | 'resort'
            $table->string('listing_kind', 16)->default('property')->after('reference')->index();

            $table->string('resort_name', 200)->nullable()->after('property_type');
            $table->string('club_or_developer', 160)->nullable()->after('resort_name');
            // e.g. "Week 32, fixed" or "120,000 points a year".
            $table->string('ownership_details', 200)->nullable()->after('club_or_developer');
        });
    }

    public function down(): void
    {
        Schema::table('hosting_enquiries', function (Blueprint $table) {
            $table->dropIndex(['listing_kind']);
            $table->dropColumn(['listing_kind', 'resort_name', 'club_or_developer', 'ownership_details']);
        });
    }
};
