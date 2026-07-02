<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * client_secret was a Stripe Elements concept (scoped confirmation token
     * for client-side confirmPayment). NMI's Collect.js flow tokenizes in the
     * browser with the public tokenization key and never needs a per-intent
     * secret, so the column goes away with the Stripe removal (2026-07).
     */
    public function up(): void
    {
        Schema::table('payment_intents', function (Blueprint $table) {
            $table->dropColumn('client_secret');
        });
    }

    public function down(): void
    {
        Schema::table('payment_intents', function (Blueprint $table) {
            $table->string('client_secret', 255)->nullable()->after('external_intent_id');
        });
    }
};
