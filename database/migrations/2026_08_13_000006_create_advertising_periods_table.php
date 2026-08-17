<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The window during which a property is actually advertised.
 *
 * A paid order records that a member bought N weeks. It does not record WHICH
 * property those weeks cover or WHEN the advertising runs — so until now the
 * system could say "they paid $1,796" and nothing else. Every question staff
 * actually ask ("is this ad live?", "when does it expire?", "how many days do
 * they have left?") needs this row.
 *
 * It is also the fulfilment half of a chargeback defence. A receipt proves a
 * charge; this proves the service was delivered, for a stated period, against
 * a named property.
 *
 * starts_at/ends_at are stored rather than derived from the order's week count
 * for the same reason the order snapshots its price: staff can extend or pause
 * a period, and the record must say what actually happened, not what the
 * original arithmetic would imply.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertising_periods', function (Blueprint $table) {
            $table->id();

            $table->foreignId('member_service_order_id')
                ->constrained('member_service_orders')
                ->cascadeOnDelete();

            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();

            $table->timestamp('starts_at');
            $table->timestamp('ends_at');

            // Who turned it on, and when. The activation timestamp is separate
            // from starts_at: staff may activate today for a window beginning
            // next Monday, and a dispute needs both facts.
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('activated_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // pending -> active -> expired, or paused/cancelled by staff.
            $table->string('status', 16)->default('pending');

            $table->timestamp('paused_at')->nullable();
            $table->text('staff_notes')->nullable();

            $table->timestamps();

            $table->index(['status', 'ends_at']);
            $table->index('property_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertising_periods');
    }
};
