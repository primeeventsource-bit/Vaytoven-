<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();

            // FK to users — left as a plain column (no constraint) so this
            // migration runs before the auth scaffolding lands. Add an
            // explicit foreign key once App\Models\User exists.
            $table->unsignedBigInteger('user_id')->nullable()->index();

            // Snapshot of client identity at send time (kept even if the
            // associated user is later deleted, for legal/audit traceability).
            $table->string('client_name', 160);
            $table->string('client_email', 200);
            $table->string('client_phone', 40)->nullable();

            // What kind of agreement this is. Free-form string; controller
            // gates on a known list (host_listing, member_program, booking_terms, custom).
            $table->string('contract_type', 60)->index();
            $table->string('title', 200);

            // DocuSign linkage. template_id is null for ad-hoc document uploads;
            // envelope_id is set on send and is the canonical join to DocuSign.
            $table->string('template_id', 80)->nullable();
            $table->string('envelope_id', 80)->nullable()->unique();

            // Lifecycle status. Mirrors DocuSign envelope statuses:
            // draft, sent, delivered, viewed, signed, completed, declined, voided, expired.
            $table->string('status', 24)->default('draft')->index();

            // Where the request originated (web | app | admin).
            $table->string('source', 16)->default('admin')->index();

            // Optional linkage to a payment/invoice in Stripe, internal billing, etc.
            $table->string('payment_id', 120)->nullable()->index();

            // Independent terms acceptance — stored separately because some
            // jurisdictions require an explicit ToS click-through prior to signing.
            $table->timestamp('terms_accepted_at')->nullable();

            // Lifecycle timestamps. All nullable; populated by webhook updates.
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            // Storage paths to the signed PDF and the DocuSign Certificate of
            // Completion. Kept as relative paths under the configured filesystem disk.
            $table->string('signed_pdf_path', 500)->nullable();
            $table->string('certificate_pdf_path', 500)->nullable();

            // Most-recent signer environment captured from DocuSign Connect events.
            // Per-event history lives in contract_events.
            $table->string('last_signer_ip', 45)->nullable();
            $table->text('last_signer_user_agent')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['client_email', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
