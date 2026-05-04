<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('contract_id')
                ->constrained('contracts')
                ->cascadeOnDelete();

            // sent | delivered | viewed | signed | completed | declined |
            // voided | authentication_failed | reassigned | resent
            $table->string('event_type', 40)->index();

            // Time the event happened on DocuSign's side (may differ from when
            // we received the webhook).
            $table->timestamp('occurred_at')->index();

            // DocuSign recipient identifier this event refers to (envelopes can
            // have multiple signers — host countersignature, witness, etc.).
            $table->string('recipient_id', 80)->nullable();
            $table->string('recipient_email', 200)->nullable();

            // Signer environment captured per event so the audit trail shows
            // not just "signed" but where + how each signature happened.
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            // Full webhook payload preserved for forensics. Use json column so
            // rows can be queried by JSON path (Postgres / MySQL 5.7+).
            $table->json('raw_payload')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_events');
    }
};
