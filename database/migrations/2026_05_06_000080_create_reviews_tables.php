<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings');
            $table->foreignId('property_id')->constrained('properties');
            $table->foreignId('author_user_id')->constrained('users');
            $table->enum('author_role', ['traveler', 'host']);
            $table->unsignedTinyInteger('rating');           // 1–5
            $table->mediumText('body')->nullable();
            $table->boolean('is_visible')->default(false);   // visible after both review or 14d
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['booking_id', 'author_user_id']); // 1 review per booking per author
            $table->index('property_id');
            $table->index('is_visible');
        });

        Schema::create('review_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->unique()->constrained('reviews')->cascadeOnDelete();
            $table->foreignId('responder_user_id')->constrained('users');
            $table->mediumText('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_responses');
        Schema::dropIfExists('reviews');
    }
};
