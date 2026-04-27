<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members_enquiries', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Contact
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('email');
            $table->string('phone', 40);

            // Portfolio detail
            $table->string('program', 120);                    // e.g. "Marriott Vacation Club"
            $table->text('property');                          // resort + location, free-text
            $table->string('annual_points', 40)->nullable();   // free-text on submit
            $table->string('best_time_to_call', 40)->nullable();
            $table->text('notes')->nullable();

            // Consent + provenance
            $table->boolean('consent_given')->default(false);
            $table->timestamp('consent_at')->nullable();
            $table->string('source', 40)->default('website');  // website | app_search | app_host | admin | import
            $table->text('referrer_url')->nullable();
            $table->text('user_agent')->nullable();
            $table->ipAddress('ip_address')->nullable();

            // Workflow
            $table->string('status', 30)->default('new');       // new|contacted|qualified|onboarded|rejected|unresponsive|duplicate
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('qualified_at')->nullable();
            $table->timestamp('onboarded_at')->nullable();
            $table->text('rejection_reason')->nullable();

            // Conversion
            $table->uuid('converted_property_id')->nullable();

            // Spam/quality (FR-9.9)
            $table->integer('spam_score')->default(0);          // higher = more suspicious
            $table->boolean('flagged')->default(false);

            // Timestamps + soft delete
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('email');
            $table->index('status');
            $table->index('assigned_to');
            $table->index('created_at');
            $table->index(['flagged', 'status']);
        });

        // CHECK constraint on status (Postgres syntax). On MySQL/SQLite this will be skipped.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE members_enquiries
                ADD CONSTRAINT chk_members_enquiries_status
                CHECK (status IN ('new','contacted','qualified','onboarded','rejected','unresponsive','duplicate'))
            ");
            DB::statement("
                ALTER TABLE members_enquiries
                ADD CONSTRAINT chk_members_enquiries_source
                CHECK (source IN ('website','app_search','app_host','admin','import'))
            ");
            // FK to properties added separately so this migration doesn't depend on table order
            DB::statement("
                ALTER TABLE members_enquiries
                ADD CONSTRAINT fk_members_enquiries_property
                FOREIGN KEY (converted_property_id)
                REFERENCES properties(id) ON DELETE SET NULL
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('members_enquiries');
    }
};
