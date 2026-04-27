<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amenities', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('name', 80);
            $table->string('category', 40);
            $table->string('icon', 60)->nullable();
            $table->boolean('is_safety')->default(false);
            $table->timestamps();
        });

        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('host_id')->constrained('users')->restrictOnDelete();
            $table->string('title', 160);
            $table->string('slug', 200)->unique();
            $table->text('description');
            $table->string('property_type', 40);
            $table->string('room_type', 40);
            $table->unsignedSmallInteger('max_guests');
            $table->decimal('bedrooms', 3, 1);
            $table->decimal('bathrooms', 3, 1);
            $table->unsignedSmallInteger('beds');

            $table->string('country_code', 2);
            $table->string('region', 80)->nullable();
            $table->string('city', 80);
            $table->string('postal_code', 20)->nullable();
            $table->text('address_line1')->nullable();
            $table->text('address_line2')->nullable();
            // Geographic coordinates - PostGIS in Postgres, decimal columns in MySQL
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);

            // Money is stored in cents to avoid float drift
            $table->unsignedInteger('base_price_cents');
            $table->unsignedInteger('cleaning_fee_cents')->default(0);
            $table->unsignedInteger('extra_guest_fee_cents')->default(0);
            $table->unsignedSmallInteger('extra_guests_after')->default(0);
            $table->char('currency', 3)->default('USD');

            $table->unsignedSmallInteger('min_nights')->default(1);
            $table->unsignedSmallInteger('max_nights')->default(28);
            $table->unsignedSmallInteger('advance_notice_hours')->default(0);
            $table->time('check_in_time')->default('15:00');
            $table->time('check_out_time')->default('11:00');

            $table->boolean('instant_book')->default(false);
            $table->string('cancellation_policy', 30)->default('moderate');

            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();

            $table->decimal('rating_avg', 3, 2)->nullable();
            $table->unsignedInteger('rating_count')->default(0);
            $table->unsignedInteger('booking_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index(['country_code', 'city']);
            $table->index('host_id');
            $table->index('property_type');
            $table->index(['latitude', 'longitude']);
        });

        if (DB::getDriverName() === 'pgsql') {
            // Add PostGIS geometry column for spatial queries
            DB::statement('ALTER TABLE properties ADD COLUMN location geography(POINT, 4326)');
            DB::statement('CREATE INDEX properties_location_idx ON properties USING GIST (location)');
            // Status check
            DB::statement("ALTER TABLE properties ADD CONSTRAINT properties_status_check
                CHECK (status IN ('draft','pending_review','active','snoozed','removed'))");
            DB::statement("ALTER TABLE properties ADD CONSTRAINT properties_cancellation_check
                CHECK (cancellation_policy IN ('flexible','moderate','strict','non_refundable'))");
        }

        Schema::create('property_amenities', function (Blueprint $table) {
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('amenity_id')->constrained()->restrictOnDelete();
            $table->primary(['property_id', 'amenity_id']);
        });

        Schema::create('property_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->text('url');
            $table->text('thumbnail_url')->nullable();
            $table->string('alt_text', 200)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_cover')->default(false);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();

            $table->index(['property_id', 'sort_order']);
        });

        Schema::create('property_calendar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->boolean('is_available')->default(true);
            $table->boolean('is_blocked_by_host')->default(false);
            $table->foreignId('booking_id')->nullable();
            $table->unsignedInteger('price_override_cents')->nullable();
            $table->unsignedSmallInteger('min_nights_override')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['property_id', 'date']);
            $table->index('date');
        });

        // Seed canonical amenities catalog
        $now = now();
        DB::table('amenities')->insert([
            ['slug' => 'wifi',             'name' => 'Wi-Fi',             'category' => 'essentials', 'icon' => 'wifi',     'is_safety' => false, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'kitchen',          'name' => 'Kitchen',           'category' => 'essentials', 'icon' => 'kitchen',  'is_safety' => false, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'air_conditioning', 'name' => 'Air conditioning',  'category' => 'climate',    'icon' => 'snow',     'is_safety' => false, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'heating',          'name' => 'Heating',           'category' => 'climate',    'icon' => 'flame',    'is_safety' => false, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'pool',             'name' => 'Pool',              'category' => 'features',   'icon' => 'pool',     'is_safety' => false, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'hot_tub',          'name' => 'Hot tub',           'category' => 'features',   'icon' => 'spa',      'is_safety' => false, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'beachfront',       'name' => 'Beachfront',        'category' => 'location',   'icon' => 'beach',    'is_safety' => false, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'free_parking',     'name' => 'Free parking',      'category' => 'features',   'icon' => 'car',      'is_safety' => false, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'workspace',        'name' => 'Dedicated workspace','category' => 'features',  'icon' => 'desk',     'is_safety' => false, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'washer',           'name' => 'Washer',            'category' => 'features',   'icon' => 'washer',   'is_safety' => false, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'dryer',            'name' => 'Dryer',             'category' => 'features',   'icon' => 'dryer',    'is_safety' => false, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'tv',               'name' => 'TV',                'category' => 'features',   'icon' => 'tv',       'is_safety' => false, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'pet_friendly',     'name' => 'Pet friendly',      'category' => 'policy',     'icon' => 'paw',      'is_safety' => false, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'smoke_detector',   'name' => 'Smoke detector',    'category' => 'safety',     'icon' => 'smoke',    'is_safety' => true,  'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'co_detector',      'name' => 'Carbon monoxide alarm','category' => 'safety',  'icon' => 'co',       'is_safety' => true,  'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'fire_extinguisher','name' => 'Fire extinguisher', 'category' => 'safety',     'icon' => 'extinguisher','is_safety' => true,'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'first_aid_kit',    'name' => 'First aid kit',     'category' => 'safety',     'icon' => 'medkit',   'is_safety' => true,  'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('property_calendar');
        Schema::dropIfExists('property_images');
        Schema::dropIfExists('property_amenities');
        Schema::dropIfExists('properties');
        Schema::dropIfExists('amenities');
    }
};
