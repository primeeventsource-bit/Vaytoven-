<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_intents', function (Blueprint $table) {
            // Stripe's PaymentIntent.client_secret. Despite the name, it's not
            // a secret in the credentials sense — it's scoped to a single
            // PaymentIntent and only allows that intent to be confirmed
            // client-side. Stashing it lets the pay page render without an
            // extra round-trip to Stripe to re-fetch.
            $table->string('client_secret', 255)->nullable()->after('external_intent_id');
        });
    }

    public function down(): void
    {
        Schema::table('payment_intents', function (Blueprint $table) {
            $table->dropColumn('client_secret');
        });
    }
};
