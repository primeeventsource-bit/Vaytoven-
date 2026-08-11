<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Host/Guest service fee structure.
 *
 * TWO decisions worth stating, because both are load-bearing:
 *
 * 1. BASIS POINTS, not whole percent. The required rates are 3%, 15.5% and a
 *    guest band of 14.1%–16.5% — none of which a whole integer can express,
 *    and floats are banned for money maths (NFR-1). Taxes in this codebase
 *    already use bps (`tax_rules.rate_bps`, 875 = 8.75%), so this follows the
 *    existing convention rather than inventing a third one.
 *      300 = 3%   1550 = 15.5%   1410 = 14.1%   1650 = 16.5%
 *
 * 2. A GENERIC SCOPE, not one column per override dimension. The requirement
 *    asks for overrides per host, property, property category and membership
 *    plan. Property category and membership plan do not exist as linkable
 *    concepts yet (`property_types` has no FK from `properties`, and there is
 *    no membership-plan table at all). Modelling scope as (scope_type,
 *    scope_id) means adding those later is a row, not a migration — and it
 *    avoids pretending today that a dimension is wired when it is not.
 *
 * The booking snapshot columns exist because the requirement is explicit that
 * changing the percentages must not alter historical transactions. Storing
 * only the resulting cents would make a past booking unreconstructable, so the
 * RATES are snapshotted alongside the amounts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_fee_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);

            // default | listing_source | host | property
            // (+ property_type / membership_plan once those exist)
            $table->string('scope_type', 32)->default('default');
            // Null for 'default'. An id for host/property; a string key such as
            // 'managed' for listing_source — stored as a string so one column
            // serves both without a polymorphic join.
            $table->string('scope_value', 64)->nullable();

            $table->string('fee_structure', 16)->default('split');

            // Basis points. 10000 = 100%.
            $table->unsignedInteger('split_host_bps')->default(300);      // 3%
            $table->unsignedInteger('split_guest_bps')->default(1500);    // 15%
            $table->unsignedInteger('single_host_bps')->default(1550);    // 15.5%

            // Effective dating: a config applies from effective_from until
            // effective_to (null = open-ended). Lets an operator schedule a
            // change without editing the live row.
            $table->timestamp('effective_from')->nullable()->index();
            $table->timestamp('effective_to')->nullable();

            $table->boolean('active')->default(true)->index();
            $table->string('notes', 500)->nullable();

            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['scope_type', 'scope_value']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            // Immutable snapshot of the fee configuration actually applied.
            // Nullable because bookings taken before this feature have none —
            // and back-filling them with today's rates would be a fabrication.
            $table->string('fee_structure', 16)->nullable()->after('tax_cents');
            $table->unsignedInteger('host_fee_bps')->nullable()->after('fee_structure');
            $table->unsignedInteger('host_fee_cents')->nullable()->after('host_fee_bps');
            $table->unsignedInteger('guest_fee_bps')->nullable()->after('host_fee_cents');
            $table->unsignedInteger('host_net_cents')->nullable()->after('guest_fee_bps');
            // Which config produced these numbers, for audit. nullOnDelete so
            // deleting a config never destroys a booking's history.
            $table->foreignId('service_fee_config_id')->nullable()->after('host_net_cents')
                ->constrained('service_fee_configs')->nullOnDelete();
        });

        // Host-level default assignment, so an operator can put one host on
        // Single-Fee without creating a scoped config row.
        Schema::table('users', function (Blueprint $table) {
            $table->string('fee_structure', 16)->nullable()->after('role');
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->string('fee_structure', 16)->nullable()->after('listing_source');
        });
    }

    public function down(): void
    {
        Schema::table('properties', fn (Blueprint $t) => $t->dropColumn('fee_structure'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('fee_structure'));

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_fee_config_id');
            $table->dropColumn([
                'fee_structure', 'host_fee_bps', 'host_fee_cents',
                'guest_fee_bps', 'host_net_cents',
            ]);
        });

        Schema::dropIfExists('service_fee_configs');
    }
};
