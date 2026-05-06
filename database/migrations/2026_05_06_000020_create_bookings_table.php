<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties');
            $table->foreignId('traveler_id')->constrained('users');
            // VYT- + 6 uppercase alphanumeric (FR-3.3) — server-generated, globally unique.
            $table->string('confirmation_code', 10)->unique();
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->unsignedTinyInteger('guests');
            // Money: integer cents only (NFR-1 hard rule).
            $table->unsignedInteger('nightly_rate_cents');     // snapshot at booking time
            $table->unsignedSmallInteger('nights');
            $table->unsignedInteger('subtotal_cents');
            $table->unsignedInteger('cleaning_fee_cents')->default(0);
            $table->unsignedInteger('service_fee_cents')->default(0);
            $table->unsignedInteger('tax_cents')->default(0);
            $table->unsignedInteger('total_cents');
            $table->enum('cancellation_policy', ['flexible', 'moderate', 'strict', 'non_refundable']);
            $table->enum('status', [
                'pending_payment', 'confirmed', 'in_progress', 'completed', 'cancelled',
            ])->default('pending_payment');
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancelled_reason')->nullable();
            $table->unsignedBigInteger('payment_intent_id')->nullable();
            $table->timestamps();

            // Cheap duplicate detection. Real overlap prevention enforced at the
            // application layer via SELECT ... FOR UPDATE inside a transaction
            // (see BookingService — Phase 3). This catches the trivial case of
            // two bookings asking for *exactly* the same date range.
            $table->unique(
                ['property_id', 'check_in_date', 'check_out_date'],
                'uk_no_identical_overlap'
            );
            $table->index('traveler_id');
            $table->index('status');
            $table->index('check_in_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
