<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin Settings & Configuration subsystem — Layer 2 (managed collections).
 *
 * Entity tables the operations console CRUDs directly. Notes:
 *
 *  - payment_processors.code matches App\Enums\PaymentProcessor values
 *    exactly ('authorizenet', 'mes', …) so rows join cleanly against
 *    payment_intents.processor and DisputeAdapterRegistry keys.
 *  - payment_processors.credentials is encrypted at rest (model cast);
 *    never returned in plaintext by any endpoint.
 *  - cancellation_policies.refund_tiers is [{days_before, refund_pct}, …],
 *    percentages whole integers. Seed data reproduces the previously
 *    hardcoded RefundCalculator tiers exactly.
 *  - All money integer cents; all percentages whole ints; tax rates in
 *    basis points (875 = 8.75%). No floats anywhere (NFR-1).
 *  - email/sms templates are versioned: editing a template creates a new
 *    row with version+1; used versions are never mutated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_processors', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique('payment_processors_code_unique');
            $table->string('display_name', 96);
            $table->boolean('enabled')->default(false);
            $table->string('mode', 8)->default('test'); // test | live
            $table->integer('priority')->default(100);  // lower = tried first
            $table->text('credentials')->nullable();    // encrypted JSON (model cast)
            $table->boolean('supports_connect')->default(false);
            $table->integer('min_amount_cents')->nullable();
            $table->integer('max_amount_cents')->nullable();
            $table->json('currencies')->nullable();     // allowed ISO codes
            $table->boolean('is_default')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['enabled', 'priority'], 'payment_processors_routing_idx');
        });

        Schema::create('cancellation_policies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique('cancellation_policies_code_unique');
            $table->string('name', 96);
            $table->text('description')->nullable();
            $table->json('refund_tiers'); // [{days_before:int, refund_pct:int 0..100}, ...]
            $table->boolean('is_default')->default(false);
            $table->boolean('active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('fee_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 96);
            $table->unsignedInteger('guest_service_pct')->default(12);   // whole percent
            $table->unsignedInteger('host_commission_pct')->default(3);  // whole percent
            $table->string('cleaning_fee_mode', 16)->default('host_set'); // host_set | flat | none
            $table->integer('flat_cleaning_fee_cents')->nullable();
            $table->string('security_deposit_mode', 8)->default('none');  // none | flat | pct
            $table->integer('security_deposit_cents')->nullable();
            $table->string('applies_to', 8)->default('all'); // all | host | mlp
            $table->boolean('active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('tax_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 96);
            $table->string('jurisdiction', 96);
            $table->string('country_code', 2);
            $table->string('region_code', 8)->nullable();
            $table->unsignedInteger('rate_bps'); // basis points, 875 = 8.75%
            $table->string('applies_to', 16)->default('booking_total'); // booking_total | service_fee | nightly
            $table->boolean('active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['active', 'country_code'], 'tax_rules_active_country_idx');
        });

        Schema::create('vacation_club_programs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128);
            $table->string('code', 64)->unique('vacation_club_programs_code_unique');
            $table->boolean('points_based')->default(true);
            $table->boolean('active')->default(true);
            $table->text('enquiry_notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 96);
            $table->string('name', 128);
            $table->string('subject', 255)->nullable();
            $table->text('body_markdown');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('active')->default(true);
            $table->json('variables')->nullable(); // documented merge tags
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['key', 'version'], 'email_templates_key_version_unique');
        });

        Schema::create('sms_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 96);
            $table->string('name', 128);
            $table->text('body_text');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('active')->default(true);
            $table->json('variables')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['key', 'version'], 'sms_templates_key_version_unique');
        });

        Schema::create('property_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique('property_types_slug_unique');
            $table->string('label', 96);
            $table->string('icon', 64)->nullable();
            $table->boolean('active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_types');
        Schema::dropIfExists('sms_templates');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('vacation_club_programs');
        Schema::dropIfExists('tax_rules');
        Schema::dropIfExists('fee_schedules');
        Schema::dropIfExists('cancellation_policies');
        Schema::dropIfExists('payment_processors');
    }
};
