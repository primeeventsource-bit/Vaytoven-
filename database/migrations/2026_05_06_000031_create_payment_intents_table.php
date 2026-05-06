<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_intents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings');
            $table->enum('processor', [
                'stripe', 'authorizenet', 'nmi', 'nuvei', 'mes',
                'paymentcloud', 'ems', 'nexio', 'netevia', 'kurv',
            ])->default('stripe');
            $table->string('external_intent_id', 128);
            $table->unsignedInteger('amount_cents'); // money: integer cents
            $table->char('currency', 3)->default('USD');
            $table->enum('status', [
                'requires_action', 'processing', 'succeeded',
                'requires_payment_method', 'canceled', 'failed',
            ])->default('processing');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['processor', 'external_intent_id']);
            $table->index('booking_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_intents');
    }
};
