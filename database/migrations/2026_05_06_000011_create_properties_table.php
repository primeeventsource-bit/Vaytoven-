<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('host_id')->constrained('users');
            $table->enum('listing_source', ['host', 'managed'])->default('host');
            $table->string('title');
            $table->mediumText('description')->nullable();
            // Lat/lng stored as DECIMAL for portability (SQLite tests + MySQL prod).
            // Bounding-box prefilter via app code; spatial indexing added in
            // a follow-up MySQL-only migration once we measure search load.
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('address_line')->nullable();
            $table->string('city', 128)->nullable();
            $table->string('region', 128)->nullable();
            $table->char('country', 2)->nullable();           // ISO-3166 alpha-2
            $table->string('postal_code', 32)->nullable();
            $table->unsignedTinyInteger('capacity')->default(1);
            $table->unsignedTinyInteger('bedrooms')->default(1);
            $table->unsignedTinyInteger('beds')->default(1);
            $table->decimal('bathrooms', 3, 1)->default(1.0); // display only — not money
            // Money in integer cents only (NFR / hard rule).
            $table->unsignedInteger('base_nightly_cents');
            $table->unsignedInteger('cleaning_fee_cents')->default(0);
            $table->enum('cancellation_policy', ['flexible', 'moderate', 'strict', 'non_refundable'])
                ->default('moderate');
            $table->unsignedTinyInteger('minimum_nights')->default(1);
            $table->enum('status', ['draft', 'pending_review', 'active', 'paused', 'archived'])
                ->default('draft');
            $table->unsignedBigInteger('payout_account_id')->nullable();
            $table->unsignedBigInteger('converted_from_enquiry_id')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['city', 'country']);
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
