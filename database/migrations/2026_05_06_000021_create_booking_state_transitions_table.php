<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_state_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->string('from_state', 32)->nullable(); // null on initial creation
            $table->string('to_state', 32);
            $table->foreignId('actor_user_id')->nullable()->constrained('users');
            $table->string('reason')->nullable();
            $table->timestamp('occurred_at');

            $table->index('booking_id');
            $table->index(['booking_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_state_transitions');
    }
};
