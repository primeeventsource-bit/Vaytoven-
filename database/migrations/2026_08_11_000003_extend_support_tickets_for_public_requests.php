<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let `support_tickets` carry a Trip Support request submitted from the public
 * site, not just one opened by the AI chat agent.
 *
 * Two gaps to close:
 *   - The submitter may not be signed in, so opened_by_user_id can be null and
 *     there was nowhere to record who to reply to.
 *   - The spec requires a reference number the customer can quote. The primary
 *     key is not that: it leaks how many tickets exist and is easy to mistype.
 *
 * Reusing this table rather than adding a parallel `trip_support_requests` one
 * keeps every support request in a single queue with a single status vocabulary,
 * which is what an operator actually wants to work from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            // e.g. VYT-S-4K9WQ2. Nullable because chat-opened tickets predate
            // it; the model backfills one on create from here on.
            $table->string('reference', 24)->nullable()->unique()->after('id');

            // Where it came from: 'chat' (the AI agent) or 'trip_support'.
            $table->string('source', 24)->default('chat')->after('reference')->index();

            // Reply-to details for a submitter who is not signed in.
            $table->string('contact_name', 160)->nullable()->after('opened_by_user_id');
            $table->string('contact_email', 160)->nullable()->after('contact_name');
            $table->string('contact_phone', 40)->nullable()->after('contact_email');

            // Free-text so a guest can quote a confirmation code, a listing
            // title, or a URL — whatever they have to hand.
            $table->string('property_reference', 255)->nullable()->after('contact_phone');
            $table->string('category', 40)->nullable()->after('property_reference')->index();

            $table->string('ip', 45)->nullable()->after('resolution_note');
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropColumn([
                'reference', 'source', 'contact_name', 'contact_email',
                'contact_phone', 'property_reference', 'category', 'ip',
            ]);
        });
    }
};
