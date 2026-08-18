<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Everything the listing builder edits, beyond what a booking-era property row
 * carried.
 *
 * The existing columns describe a rental unit: capacity, nightly rate, minimum
 * nights, cancellation policy. What is advertised now is a property and the
 * time available on it, presented by staff on a member's behalf, so the row
 * has to carry the things a person reads before making an offer and the things
 * staff need to administer the advertisement.
 *
 * Deliberately one migration rather than eight. These columns are meaningless
 * apart from each other - a listing with a headline and no short description
 * is not a half-built listing, it is a broken one - and splitting them would
 * leave several deploys where the builder can save some fields and silently
 * drop the rest.
 *
 * Names avoid two collisions worth knowing about:
 *   - `type` belongs to the PropertyType model, which is exchange-detection
 *     taxonomy, not what a guest would call a villa. This is `property_kind`.
 *   - `minimum_nights` already exists and means what the spec calls minimum
 *     stay, so it is reused rather than duplicated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // --- basics -----------------------------------------------------
            // Human-quotable id. Staff read this down a phone line, so it is
            // stored rather than derived: a derived id changes if the scheme
            // does, and the one on the member's paperwork would stop matching.
            $table->string('reference', 20)->nullable()->unique()->after('id');
            $table->string('property_kind', 32)->nullable()->after('listing_source');
            $table->string('resort_name', 160)->nullable()->after('title');

            // Which paid order this advertisement belongs to, and where it sits
            // in that package's allowance. Nullable: staff draft listings
            // before an order is paid, and a listing must not require one to
            // exist.
            $table->foreignId('member_service_order_id')->nullable()->after('host_id')
                ->constrained('member_service_orders')->nullOnDelete();
            $table->unsignedSmallInteger('position_in_package')->nullable()->after('member_service_order_id');

            // How precisely the public map may show this. A member advertising
            // a home they still live in has a real interest in the pin not
            // being their front door.
            $table->string('location_precision', 16)->default('approximate')->after('longitude');

            // --- details ----------------------------------------------------
            $table->unsignedInteger('square_feet')->nullable()->after('bathrooms');
            $table->string('floor_unit', 60)->nullable()->after('square_feet');
            $table->text('bed_configuration')->nullable()->after('beds');
            $table->string('check_in_day', 16)->nullable()->after('minimum_nights');
            $table->string('check_in_time', 16)->nullable()->after('check_in_day');
            $table->string('check_out_time', 16)->nullable()->after('check_in_time');
            $table->string('unit_size_type', 80)->nullable()->after('check_out_time');
            $table->string('view_type', 60)->nullable()->after('unit_size_type');
            $table->text('accessibility_notes')->nullable()->after('view_type');
            $table->string('pet_policy', 160)->nullable()->after('accessibility_notes');
            $table->string('smoking_policy', 160)->nullable()->after('pet_policy');
            $table->string('parking_info', 255)->nullable()->after('smoking_policy');

            // --- description builder ----------------------------------------
            $table->string('headline', 180)->nullable()->after('resort_name');
            $table->text('short_description')->nullable()->after('headline');
            // A list, so it renders as bullets everywhere rather than each
            // surface re-parsing a blob of text its own way.
            $table->json('highlights')->nullable()->after('description');

            // --- offer settings ---------------------------------------------
            $table->boolean('allow_offers')->default(true);
            $table->boolean('allow_inquiries')->default(true);
            $table->boolean('display_suggested_amount')->default(false);
            $table->unsignedInteger('minimum_offer_cents')->nullable();
            $table->boolean('require_guest_count')->default(true);
            $table->boolean('require_message')->default(true);
        });

        // Backfill references for rows that predate the column, so no live
        // listing is left without an id staff can quote.
        // Random, not derived from the id, matching how new rows are generated
        // and how order and offer references already work. A sequential id on
        // a public listing tells anyone who looks how many properties exist.
        $used = [];
        DB::table('properties')->whereNull('reference')->orderBy('id')->each(function ($row) use (&$used) {
            do {
                $reference = 'VAY-P-'.random_int(10000, 99999);
            } while (
                isset($used[$reference])
                || DB::table('properties')->where('reference', $reference)->exists()
            );

            $used[$reference] = true;
            DB::table('properties')->where('id', $row->id)->update(['reference' => $reference]);
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropConstrainedForeignId('member_service_order_id');
            $table->dropColumn([
                'reference', 'property_kind', 'resort_name', 'position_in_package',
                'location_precision', 'square_feet', 'floor_unit', 'bed_configuration',
                'check_in_day', 'check_in_time', 'check_out_time', 'unit_size_type',
                'view_type', 'accessibility_notes', 'pet_policy', 'smoking_policy',
                'parking_info', 'headline', 'short_description', 'highlights',
                'allow_offers', 'allow_inquiries', 'display_suggested_amount',
                'minimum_offer_cents', 'require_guest_count', 'require_message',
            ]);
        });
    }
};
