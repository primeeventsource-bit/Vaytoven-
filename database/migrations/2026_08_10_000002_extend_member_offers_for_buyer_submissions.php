<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Buyer inquiries and offers, added to `member_offers` rather than a parallel
 * table.
 *
 * The table already models "an offer against a listing, with an amount, a
 * status, an expiry, and a response" — the only thing genuinely new is WHO
 * originates it. So this adds a `direction` discriminator:
 *
 *   to_member  (existing) Vaytoven proposes a booking to the member who owns
 *              the managed listing; the MEMBER responds.
 *   from_buyer (new)      A buyer submits an inquiry or offer on a public
 *              listing; the LISTING OWNER responds.
 *
 * One table means one expiry sweep, one audit trail, and one place to look
 * when an offer's history is questioned.
 *
 * Three existing NOT NULL columns are widened to nullable because they only
 * make sense for the outbound direction: a buyer inquiry may carry no dates
 * and no member payout. Widening is backwards-compatible — every existing row
 * keeps its value and every existing write still satisfies the column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_offers', function (Blueprint $table) {
            // --- Which way does this offer flow? ---
            $table->string('direction', 16)->default('to_member')->after('id');

            // 'offer' carries an amount; 'inquiry' is a question with no price.
            $table->string('kind', 16)->default('offer')->after('direction');

            // --- Buyer side (null on to_member rows) ---
            $table->foreignId('buyer_user_id')->nullable()->after('member_user_id')
                ->constrained('users')->nullOnDelete();

            // Money: integer cents only (hard rule). Distinct from
            // payout_to_member_cents, which is what Vaytoven pays OUT.
            $table->unsignedBigInteger('offer_amount_cents')->nullable()->after('payout_to_member_cents');

            $table->text('buyer_message')->nullable()->after('instructions');

            // IPv6-safe. Captured at submission for dispute evidence.
            $table->string('submitted_ip', 45)->nullable()->after('buyer_message');

            $table->index(['direction', 'status']);
            $table->index('buyer_user_id');
        });

        // Widen the outbound-only columns. Done in a second statement because
        // change() and add-column can't share a Blueprint safely on MySQL.
        Schema::table('member_offers', function (Blueprint $table) {
            $table->date('proposed_check_in')->nullable()->change();
            $table->date('proposed_check_out')->nullable()->change();
            $table->unsignedBigInteger('payout_to_member_cents')->nullable()->change();
        });

        // `status` was created as a MySQL ENUM, so 'active' has to be added to
        // the column definition before any row can hold it. Rebuilt as a plain
        // string: the PHP enum cast is the real validation, and a string column
        // means the next status never needs a migration.
        $this->rewriteStatusColumn();
    }

    public function down(): void
    {
        Schema::table('member_offers', function (Blueprint $table) {
            $table->dropIndex(['direction', 'status']);
            $table->dropIndex(['buyer_user_id']);
            $table->dropConstrainedForeignId('buyer_user_id');
            $table->dropColumn([
                'direction', 'kind', 'offer_amount_cents', 'buyer_message', 'submitted_ip',
            ]);
        });
    }

    /**
     * Convert `status` from ENUM to VARCHAR. SQLite (used by the test suite)
     * has no ENUM type at all — the original column is already TEXT there — so
     * this is a no-op outside MySQL.
     */
    private function rewriteStatusColumn(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE member_offers MODIFY status VARCHAR(16) NOT NULL DEFAULT 'pending'");
    }
};
