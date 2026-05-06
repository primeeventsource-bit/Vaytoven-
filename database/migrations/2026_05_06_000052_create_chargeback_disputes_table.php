<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chargeback_disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->nullable()->constrained('bookings');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->enum('processor', [
                'stripe', 'authorizenet', 'nmi', 'nuvei', 'mes',
                'paymentcloud', 'ems', 'nexio', 'netevia', 'kurv',
            ]);
            $table->string('external_dispute_id', 128);
            $table->unsignedInteger('amount_cents'); // money: integer cents
            $table->string('reason')->nullable();
            $table->enum('status', [
                'warning_needs_response', 'needs_response', 'under_review',
                'won', 'lost', 'warning_closed',
            ]);
            $table->timestamp('evidence_due_by')->nullable();
            $table->timestamp('evidence_submitted_at')->nullable();
            $table->string('bundle_path', 512)->nullable();
            $table->timestamps();

            $table->unique(['processor', 'external_dispute_id']);
            $table->index('booking_id');
            $table->index('user_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chargeback_disputes');
    }
};
