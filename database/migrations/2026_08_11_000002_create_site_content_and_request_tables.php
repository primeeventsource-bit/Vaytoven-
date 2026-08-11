<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backing tables for the public site pages that replace the footer's
 * `href="#"` placeholders.
 *
 * The point is not to give each dead link a URL — it is to give each one real
 * content and a real destination for what a visitor does there. So:
 *
 *   contact_messages / job_applications  capture submissions, with a reference
 *                                        the customer can quote back.
 *   job_openings / press_releases        are operator-managed content, so
 *                                        Careers and Press render from the
 *                                        database and show an honest empty
 *                                        state rather than invented listings.
 *
 * Trip Support reuses the existing `support_tickets` table rather than adding
 * a fourth near-identical one; the columns it was missing (a quotable
 * reference, and contact details for someone who is not signed in) are added
 * in the second migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            // Quotable by the customer in a follow-up, e.g. VYT-C-8F2A1B.
            $table->string('reference', 24)->unique();

            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('email', 160);
            $table->string('phone', 40)->nullable();

            // Routes the message to the right team. Values validated against
            // App\Enums\ContactDepartment, not a DB enum, so adding one is a
            // code change rather than a migration.
            $table->string('department', 40)->index();
            $table->string('subject', 200);
            $table->text('message');

            $table->string('status', 20)->default('new')->index();
            $table->foreignId('handled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();

            // Provenance, same shape as members_enquiries.
            $table->string('source_url', 500)->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();
            $table->index('created_at');
        });

        Schema::create('job_openings', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 140)->unique();
            $table->string('title', 160);
            $table->string('department', 80)->index();
            $table->string('location', 120);
            // full_time | part_time | contract | internship
            $table->string('employment_type', 32);
            $table->text('summary');
            $table->longText('description');
            $table->longText('requirements')->nullable();

            // Unpublished and closed are different states: unpublished is a
            // draft nobody has seen, closed is a role that existed and no
            // longer accepts applications but stays for the record.
            $table->boolean('is_published')->default(false)->index();
            $table->timestamp('closed_at')->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 24)->unique();
            $table->foreignId('job_opening_id')->constrained('job_openings')->cascadeOnDelete();

            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('email', 160);
            $table->string('phone', 40)->nullable();
            $table->text('cover_note')->nullable();
            // Path on the configured disk. Files never go in the database.
            $table->string('resume_path', 500)->nullable();

            $table->string('status', 20)->default('new')->index();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('press_releases', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 160)->unique();
            $table->string('title', 200);
            $table->string('excerpt', 500);
            $table->longText('body');
            // Contact for this specific release; falls back to the site-wide
            // media contact setting when null.
            $table->string('media_contact_email', 160)->nullable();

            $table->boolean('is_published')->default(false)->index();
            $table->timestamp('published_at')->nullable()->index();

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('press_releases');
        Schema::dropIfExists('job_applications');
        Schema::dropIfExists('job_openings');
        Schema::dropIfExists('contact_messages');
    }
};
