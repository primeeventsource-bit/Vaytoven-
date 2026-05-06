<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->nullable()->constrained('bookings');
            $table->foreignId('traveler_id')->constrained('users');
            $table->foreignId('host_id')->constrained('users');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index('traveler_id');
            $table->index('host_id');
            $table->index('last_message_at');
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('message_threads')->cascadeOnDelete();
            $table->foreignId('sender_user_id')->constrained('users');
            $table->mediumText('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['thread_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('message_threads');
    }
};
