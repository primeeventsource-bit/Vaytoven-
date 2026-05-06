<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_intent_id')->constrained('payment_intents');
            $table->foreignId('booking_id')->constrained('bookings');
            $table->enum('processor', [
                'stripe', 'authorizenet', 'nmi', 'nuvei', 'mes',
                'paymentcloud', 'ems', 'nexio', 'netevia', 'kurv',
            ])->default('stripe');
            $table->string('external_charge_id', 128);
            $table->unsignedInteger('amount_cents');
            $table->char('currency', 3)->default('USD');
            $table->boolean('captured')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['processor', 'external_charge_id']);
            $table->index('booking_id');
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('charge_id')->constrained('charges');
            $table->foreignId('booking_id')->constrained('bookings');
            $table->foreignId('actor_user_id')->nullable()->constrained('users'); // admin or system
            $table->enum('processor', [
                'stripe', 'authorizenet', 'nmi', 'nuvei', 'mes',
                'paymentcloud', 'ems', 'nexio', 'netevia', 'kurv',
            ])->default('stripe');
            $table->string('external_refund_id', 128);
            $table->unsignedInteger('amount_cents');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['processor', 'external_refund_id']);
            $table->index('booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('charges');
    }
};
