<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "List your property" submissions.
 *
 * Replaces host payout enrollment, which described a model Vaytoven does not
 * operate — guests paying Vaytoven, funds held in escrow, ACH payouts to
 * hosts. Vaytoven advertises listings; it does not collect rental funds or pay
 * hosts, so there is nothing to enrol for and no banking details to collect.
 *
 * What an owner actually needs to do is tell us about the property. That is
 * what this captures, and it deliberately asks for NOTHING sensitive: no bank
 * details, no government ID, no tax forms. A public form should never invite
 * that, and none of it is needed to advertise a listing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hosting_enquiries', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 24)->unique();

            // Set when the submitter was signed in; null for a cold enquiry.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('email', 160);
            $table->string('phone', 40)->nullable();

            // The property being offered for advertising.
            $table->string('property_name', 200)->nullable();
            $table->string('property_type', 64)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('region', 120)->nullable();
            $table->string('country', 120)->nullable();
            $table->unsignedSmallInteger('bedrooms')->nullable();
            $table->unsignedSmallInteger('bathrooms')->nullable();

            // Money: integer cents only. Indicative rate the owner has in mind,
            // not a price Vaytoven sets or collects.
            $table->unsignedBigInteger('indicative_nightly_cents')->nullable();
            $table->string('availability', 200)->nullable();
            $table->text('message')->nullable();

            $table->string('status', 20)->default('new')->index();
            $table->foreignId('handled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();

            $table->string('source_url', 500)->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hosting_enquiries');
    }
};
