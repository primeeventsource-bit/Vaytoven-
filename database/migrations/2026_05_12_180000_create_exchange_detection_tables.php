<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vacation Club Exchange Detection (FR-9.x) — backing schema.
 *
 * Three lookup tables capture the "developer brand → exchange network"
 * relationships (e.g. Marriott Vacation Club → Interval International,
 * Wyndham → RCI) used to auto-fill the banking/exchange group on member
 * enquiries and managed listings as they're created.
 *
 * Lookup data is seeded by ExchangeNetworksSeeder; admins extend via the
 * admin UI (table editor follow-up). One enquiry/property gets one
 * detection result, stored as a JSON snapshot on the row so the detection
 * stays stable even if the underlying lookup tables are revised later.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The exchange / banking networks themselves. Both true exchanges
        // (Interval International, RCI) and developer-internal programs
        // (HGV Max, Club Wyndham, Marriott Bonvoy points conversion) live
        // here, distinguished by `type`.
        Schema::create('exchange_networks', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name', 128);
            // exchange = third-party (II, RCI). internal = developer's own
            // program. banking = points-deposit/banking-only system.
            $table->enum('type', ['exchange', 'internal', 'banking'])->default('exchange');
            $table->string('website_url', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Vacation-property developer brands. "Marriott Vacation Club",
        // "Disney Vacation Club", etc. parent_brand groups by corporate
        // owner (Marriott, Disney, Hilton…) so reporting can roll up.
        Schema::create('property_developers', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name', 128);
            $table->string('parent_brand', 64)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('parent_brand');
        });

        // Many-to-many with confidence + optional conditional rules.
        // conditions JSON can carry e.g. {"locations": ["Orlando"]} or
        // {"contract_types": ["deeded"]} so a single developer can have
        // different exchanges depending on the specific resort.
        Schema::create('developer_exchange_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('developer_id')->constrained('property_developers')->cascadeOnDelete();
            $table->foreignId('exchange_network_id')->constrained('exchange_networks')->cascadeOnDelete();
            // 0-100 baseline confidence before condition bumps.
            $table->unsignedTinyInteger('confidence')->default(80);
            // Higher = preferred when multiple exchanges tie on confidence.
            $table->integer('priority')->default(0);
            $table->json('conditions')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['developer_id', 'exchange_network_id']);
        });

        // Per-row detection snapshot. Stored as JSON so the result remains
        // intact even if lookup tables are revised later (audit + admin
        // override history). Shape — see ExchangeDetectionService::detect():
        //   {
        //     "status": "auto"|"needs_review"|"confirmed"|"no_match",
        //     "matched_developer_slug": "marriott-vacation-club",
        //     "matched_developer_name": "Marriott Vacation Club",
        //     "matches": [ {"exchange_slug","exchange_name","confidence"} ],
        //     "detected_at": ISO 8601,
        //     "input_club": "...", "input_property": "...",
        //     "confirmed_by_user_id": int|null,
        //     "confirmed_at": ISO 8601|null,
        //     "confirmed_exchange_slug": "..."|null
        //   }
        Schema::table('members_enquiries', function (Blueprint $table) {
            $table->json('exchange_detection')->nullable()->after('status');
        });

        // Same shape on managed listings — fires when listing_source=managed.
        Schema::table('properties', function (Blueprint $table) {
            $table->json('exchange_detection')->nullable()->after('converted_from_enquiry_id');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('exchange_detection');
        });
        Schema::table('members_enquiries', function (Blueprint $table) {
            $table->dropColumn('exchange_detection');
        });
        Schema::dropIfExists('developer_exchange_mappings');
        Schema::dropIfExists('property_developers');
        Schema::dropIfExists('exchange_networks');
    }
};
