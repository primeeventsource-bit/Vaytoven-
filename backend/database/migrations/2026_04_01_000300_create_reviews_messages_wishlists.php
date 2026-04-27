<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewee_id')->constrained('users')->restrictOnDelete();
            $table->string('type', 20);                  // 'guest_to_host' | 'host_to_guest'
            $table->unsignedTinyInteger('overall_rating');// 1-5
            $table->unsignedTinyInteger('cleanliness_rating')->nullable();
            $table->unsignedTinyInteger('accuracy_rating')->nullable();
            $table->unsignedTinyInteger('communication_rating')->nullable();
            $table->unsignedTinyInteger('location_rating')->nullable();
            $table->unsignedTinyInteger('value_rating')->nullable();
            $table->text('public_comment')->nullable();
            $table->text('private_comment')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('hidden_by_admin')->default(false);
            $table->timestamps();

            $table->unique(['booking_id', 'reviewer_id']);
            $table->index(['property_id', 'published_at']);
            $table->index('reviewee_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE reviews ADD CONSTRAINT reviews_type_check
                CHECK (type IN ('guest_to_host','host_to_guest'))");
            DB::statement("ALTER TABLE reviews ADD CONSTRAINT reviews_overall_check
                CHECK (overall_rating BETWEEN 1 AND 5)");
        }

        Schema::create('message_threads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('guest_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('host_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->boolean('archived_by_guest')->default(false);
            $table->boolean('archived_by_host')->default(false);
            $table->timestamps();

            $table->index('guest_id');
            $table->index('host_id');
            $table->index('last_message_at');
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('message_threads')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->json('attachments')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->boolean('flagged')->default(false);
            $table->timestamps();

            $table->index(['thread_id', 'created_at']);
            $table->index('sender_id');
        });

        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80)->default('My Favorites');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('wishlist_properties', function (Blueprint $table) {
            $table->foreignId('wishlist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->timestamp('added_at')->useCurrent();
            $table->primary(['wishlist_id', 'property_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlist_properties');
        Schema::dropIfExists('wishlists');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('message_threads');
        Schema::dropIfExists('reviews');
    }
};
