<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('confirmation_code', 12)->unique();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('guest_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('host_id')->constrained('users')->restrictOnDelete();

            $table->date('check_in');
            $table->date('check_out');
            $table->unsignedSmallInteger('nights');
            $table->unsignedSmallInteger('guests');
            $table->unsignedSmallInteger('adults')->default(1);
            $table->unsignedSmallInteger('children')->default(0);
            $table->unsignedSmallInteger('infants')->default(0);
            $table->unsignedSmallInteger('pets')->default(0);

            // Snapshot pricing — never recalc from current property data after booking
            $table->unsignedInteger('base_price_cents');             // price * nights
            $table->unsignedInteger('cleaning_fee_cents')->default(0);
            $table->unsignedInteger('extra_guest_fee_cents')->default(0);
            $table->unsignedInteger('subtotal_cents');               // base + cleaning + extra
            $table->unsignedInteger('guest_service_fee_cents');      // 14% of subtotal
            $table->unsignedInteger('host_service_fee_cents');       // 3% of subtotal (deducted from host payout)
            $table->unsignedInteger('tax_cents')->default(0);        // 8.5% of (subtotal + cleaning)
            $table->unsignedInteger('total_cents');                  // what guest pays
            $table->unsignedInteger('host_payout_cents');            // what host receives
            $table->char('currency', 3)->default('USD');

            $table->string('cancellation_policy', 30);

            $table->string('status', 30)->default('pending');
            $table->string('payment_status', 30)->default('unpaid');

            $table->text('guest_message')->nullable();
            $table->text('host_note')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users');
            $table->timestamp('cancelled_at')->nullable();

            $table->string('stripe_payment_intent_id', 100)->nullable();
            $table->string('stripe_charge_id', 100)->nullable();

            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index(['property_id', 'check_in', 'check_out']);
            $table->index(['guest_id', 'status']);
            $table->index(['host_id', 'status']);
            $table->index('check_in');
            $table->index('status');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_status_check
                CHECK (status IN ('pending','confirmed','checked_in','completed','cancelled','declined','expired'))");
            DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_payment_status_check
                CHECK (payment_status IN ('unpaid','authorized','paid','partially_refunded','refunded','disputed'))");
            // Prevent overlapping bookings at the database level
            DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
            DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_no_overlap
                EXCLUDE USING GIST (
                    property_id WITH =,
                    daterange(check_in, check_out, '[)') WITH &&
                ) WHERE (status IN ('pending','confirmed','checked_in'))");
        }

        Schema::create('payout_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('stripe_account_id', 100)->unique();
            $table->boolean('charges_enabled')->default(false);
            $table->boolean('payouts_enabled')->default(false);
            $table->boolean('details_submitted')->default(false);
            $table->json('requirements_due')->nullable();
            $table->string('country_code', 2);
            $table->char('currency', 3);
            $table->boolean('is_default')->default(true);
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('stripe_payment_intent_id', 100)->index();
            $table->string('stripe_charge_id', 100)->nullable()->index();
            $table->unsignedInteger('amount_cents');
            $table->char('currency', 3);
            $table->string('status', 30);
            $table->json('payment_method_details')->nullable();
            $table->unsignedInteger('refunded_cents')->default(0);
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('host_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('payout_method_id')->constrained()->restrictOnDelete();
            $table->string('stripe_transfer_id', 100)->nullable()->unique();
            $table->unsignedInteger('amount_cents');
            $table->char('currency', 3);
            $table->string('status', 30)->default('scheduled');
            $table->timestamp('scheduled_for');
            $table->timestamp('released_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->timestamps();

            $table->index(['host_id', 'status']);
            $table->index(['status', 'scheduled_for']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE payouts ADD CONSTRAINT payouts_status_check
                CHECK (status IN ('scheduled','processing','paid','failed','cancelled','reversed'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payout_methods');
        Schema::dropIfExists('bookings');
    }
};
