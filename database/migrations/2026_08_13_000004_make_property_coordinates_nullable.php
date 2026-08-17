<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coordinates become optional.
 *
 * Staff create a listing on an owner's behalf from what the owner told them on
 * the phone — a name, a city, a nightly rate. Nobody has a latitude to hand at
 * that moment. Requiring one would mean either blocking the whole flow on a
 * lookup, or defaulting to 0,0 and dropping every new listing into the Gulf of
 * Guinea on the map.
 *
 * Null is the honest value for "not geocoded yet". DashboardController's map
 * query already does whereNotNull('latitude'), and the destination aggregates
 * use AVG(), which SQL evaluates ignoring nulls — so the surfaces that read
 * coordinates were already prepared for their absence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->change();
            $table->decimal('longitude', 10, 7)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Backfill before tightening, or the change fails on any row a member
        // of staff created without coordinates.
        \Illuminate\Support\Facades\DB::table('properties')
            ->whereNull('latitude')
            ->update(['latitude' => 0, 'longitude' => 0]);

        Schema::table('properties', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable(false)->change();
            $table->decimal('longitude', 10, 7)->nullable(false)->change();
        });
    }
};
