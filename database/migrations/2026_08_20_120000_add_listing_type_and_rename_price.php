<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Price is for a stay, not a night — and a listing can be a sale.
 *
 * `base_nightly_cents` described a per-night rate. Every member program stay is
 * seven days and six nights, so the figure staff actually enter is the price of
 * that stay. Leaving the column named "nightly" would mean the schema says one
 * thing while the screens say another, and the next person to read it would
 * multiply by six.
 *
 * Renamed rather than added-alongside so there is one number for the price of a
 * listing. Two columns would drift the first time somebody updated one of them.
 *
 * `listing_type` is deliberately NOT folded into `status`. Status is where the
 * listing is in the workflow — Draft, Pending Review, Active, Paused, Archived
 * — and a For Sale listing still needs all of those. Collapsing them would
 * leave no way to keep a sale listing unpublished while it is being built.
 *
 * Both steps are guarded so re-running is safe: MySQL does not roll DDL back,
 * and a migration that half-applied once must not fail on the retry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            if (! Schema::hasColumn('properties', 'listing_type')) {
                // Existing listings are rentals: that is all the product has
                // offered until now, so it is the only correct backfill.
                $table->string('listing_type', 16)->default('rent')->after('status');
            }
        });

        if (Schema::hasColumn('properties', 'base_nightly_cents')
            && ! Schema::hasColumn('properties', 'price_cents')) {
            Schema::table('properties', function (Blueprint $table) {
                $table->renameColumn('base_nightly_cents', 'price_cents');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('properties', 'price_cents')
            && ! Schema::hasColumn('properties', 'base_nightly_cents')) {
            Schema::table('properties', function (Blueprint $table) {
                $table->renameColumn('price_cents', 'base_nightly_cents');
            });
        }

        Schema::table('properties', function (Blueprint $table) {
            if (Schema::hasColumn('properties', 'listing_type')) {
                $table->dropColumn('listing_type');
            }
        });
    }
};
