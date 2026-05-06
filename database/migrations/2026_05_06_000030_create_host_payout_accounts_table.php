<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('host_payout_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('host_id')->constrained('users');
            $table->enum('processor', [
                'stripe', 'authorizenet', 'nmi', 'nuvei', 'mes',
                'paymentcloud', 'ems', 'nexio', 'netevia', 'kurv',
            ])->default('stripe');
            $table->string('external_account_id', 128); // e.g. acct_1Abc... for Stripe
            $table->enum('status', ['pending_kyc', 'verified', 'restricted', 'disabled'])->default('pending_kyc');
            $table->boolean('payouts_enabled')->default(false);
            $table->boolean('charges_enabled')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['processor', 'external_account_id']);
            $table->index('host_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('host_payout_accounts');
    }
};
