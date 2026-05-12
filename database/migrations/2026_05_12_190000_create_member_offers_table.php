<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Member Offers — Vaytoven proposes a booking against a member's points
 * inventory (their managed listing), the member accepts or declines.
 *
 * Lifecycle:
 *   1. Admin/specialist creates an offer with dates + guests + payout to
 *      the member.
 *   2. Member sees it on their dashboard; clicks Accept or Decline.
 *   3. On accept: member coordinates with their home program (Marriott,
 *      Hilton, Disney, etc.) to actually reserve the dates and add the
 *      Vaytoven guests to their guest pass list.
 *   4. Offers expire at expires_at if no response — a follow-up sweep
 *      (separate job) flips status from pending → expired.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_offers', function (Blueprint $table) {
            $table->id();
            // The member receiving the offer.
            $table->foreignId('member_user_id')->constrained('users')->cascadeOnDelete();
            // The managed listing the offer is against. Nullable so an offer
            // can be drafted before the listing is finalised.
            $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
            // Admin/specialist who created the offer. Nullable so the row
            // survives if the staff user is later deleted.
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->date('proposed_check_in');
            $table->date('proposed_check_out');
            $table->unsignedTinyInteger('proposed_guests')->default(2);

            // Money: integer cents only (hard rule).
            $table->unsignedBigInteger('payout_to_member_cents');

            $table->enum('status', ['pending', 'accepted', 'declined', 'expired', 'withdrawn'])
                ->default('pending');

            // Admin-authored notes shown to the member: things like
            // "your reservation window opens 12 months out" or
            // "reserve under our Trust account name". Kept as text so we
            // don't constrain phrasing.
            $table->text('instructions')->nullable();

            $table->timestamp('sent_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->text('member_response_notes')->nullable();

            $table->timestamps();

            $table->index(['member_user_id', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_offers');
    }
};
