<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The time being advertised on a property.
 *
 * This is the thing Vaytoven actually advertises, and until now there was
 * nowhere to record it: a property had a description and photos, and no answer
 * to "which dates?". Offers arrived against a listing rather than against a
 * period, so nothing connected an enquiry to the time it was about.
 *
 * Stored as explicit date ranges rather than week numbers. "Week 38" means
 * different dates in different programmes and different years, and the only
 * unambiguous thing to show a traveler is a start and an end date.
 *
 * Kept separate from advertising_periods, which is a different idea: that
 * table records the 180 days of service a member paid for. This records the
 * dates a guest could stay. One order's service period can carry many weeks,
 * and a week can outlive the period that advertised it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_availability_weeks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();

            $table->date('starts_on');
            $table->date('ends_on');

            // available | offer_pending | unavailable | closed
            $table->string('status', 20)->default('available');

            $table->text('notes')->nullable();

            // Who last touched it. Members may be allowed to manage their own
            // availability, so "staff changed this" and "the member changed
            // this" have to be distinguishable after the fact.
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['property_id', 'starts_on']);
            $table->index(['status', 'starts_on']);

            // The same week cannot be listed twice on one property. Without
            // this, a double-entry shows a traveler two identical rows and
            // gives staff two places to set a status that must agree.
            $table->unique(['property_id', 'starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_availability_weeks');
    }
};
