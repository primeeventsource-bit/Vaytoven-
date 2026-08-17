<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Member Services activation orders.
 *
 * This is Vaytoven billing its OWN customer for its OWN service — the money it
 * is allowed to take. It is not a booking, not a stay, and not a payment
 * between a traveler and a property owner.
 *
 * Two columns carry most of the weight:
 *
 *   price_per_week_cents  A SNAPSHOT taken when the order is created. Orders
 *                         must never be repriced by a later change to the
 *                         package rates, or a member holding a $2,694 payment
 *                         link would find it had quietly become $2,994.
 *   total_cents           Also stored rather than derived, so the amount that
 *                         was agreed is the amount that is charged even if the
 *                         weeks column is ever corrected by staff.
 *
 * No card data is stored here, ever. Collect.js tokenizes in the member's
 * browser and this application only ever sees an opaque payment token and,
 * afterwards, NMI's transaction id and the last four digits.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_service_orders', function (Blueprint $table) {
            $table->id();

            // Public identifier used in the payment URL (VTN-XXXXXXXX).
            // Random, not sequential: a sequential id in a URL lets anyone
            // walk the range and read other members' names and amounts.
            $table->string('reference', 32)->unique();

            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('email', 255);
            $table->string('phone', 40)->nullable();

            $table->string('package', 16);                      // MemberServicePackage
            $table->unsignedSmallInteger('weeks');
            $table->unsignedInteger('price_per_week_cents');     // snapshot
            $table->unsignedInteger('total_cents');              // snapshot
            $table->char('currency', 3)->default('USD');

            $table->string('status', 24)->default('awaiting_payment');
            $table->timestamp('link_expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            // NMI outcome. transaction_id is what you quote to the processor
            // when anything is disputed.
            $table->string('nmi_transaction_id', 64)->nullable();
            $table->string('nmi_authcode', 32)->nullable();
            $table->string('nmi_response_text', 255)->nullable();
            $table->string('card_last_four', 4)->nullable();
            $table->string('card_type', 32)->nullable();

            $table->unsignedTinyInteger('payment_attempts')->default(0);

            // Who submitted the activation, for fraud review.
            $table->string('submitted_ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('staff_notes')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('email');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_service_orders');
    }
};
