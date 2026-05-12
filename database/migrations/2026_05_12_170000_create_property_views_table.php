<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            // Nullable: anonymous visitors are tracked by cookie only.
            $table->foreignId('viewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            // Cookie-based visitor handle so a returning anonymous user is
            // recognised as the same person across sessions for dedupe + uniques.
            $table->string('visitor_id', 36)->nullable();
            // Geo data sourced from GeoIpService at write time. Stable for the
            // life of the row even if the GeoIP DB later disagrees.
            $table->string('ip_address', 45)->nullable();
            $table->char('country', 2)->nullable();
            $table->string('region', 64)->nullable();
            $table->string('city', 128)->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('occurred_at');

            // Per-listing time-series (the dashboard's main query shape).
            $table->index(['property_id', 'occurred_at']);
            // Dedupe + same-visitor uniques.
            $table->index(['property_id', 'visitor_id']);
            // Top-country queries on the dashboard.
            $table->index(['property_id', 'country']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_views');
    }
};
