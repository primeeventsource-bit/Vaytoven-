<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('name');
            $table->boolean('is_private')->default(true);
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('wishlist_properties', function (Blueprint $table) {
            $table->foreignId('wishlist_id')->constrained('wishlists')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->timestamp('added_at');
            $table->primary(['wishlist_id', 'property_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlist_properties');
        Schema::dropIfExists('wishlists');
    }
};
