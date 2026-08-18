<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Turn property_photos from a list of URLs into an upload store.
 *
 * The table held property_id, url, sort_order and caption — enough to point at
 * an image somebody else was hosting, and nothing else. Uploading was not
 * possible, and none of what a photo needs in an evidence system was recorded:
 * who put it there, when, what it actually is, or whether the bytes still
 * match what was uploaded.
 *
 * `url` stays and keeps working. Seeded listings point at external images and
 * turning those into broken links to tidy up a schema would be a poor trade.
 * A row now has either a url (external) or a disk+path (uploaded), and
 * displayUrl() picks.
 *
 * Two paths per upload, deliberately:
 *   path          — the optimised derivative, what visitors are served
 *   original_path — exactly what was uploaded, untouched
 * The original is kept because re-deriving from a compressed copy loses more
 * each time, and because "what did the advertisement actually show" is a
 * question the dispute-evidence system has to answer years later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_photos', function (Blueprint $table) {
            // A row now has EITHER a url (external, seeded) or a disk+path
            // (uploaded), so url can no longer be required. It stays NOT NULL
            // otherwise and every upload fails on insert.
            $table->string('url', 2048)->nullable()->change();

            $table->string('disk', 32)->nullable()->after('property_id');
            $table->string('path', 512)->nullable()->after('disk');
            $table->string('original_path', 512)->nullable()->after('path');

            // Which part of the property this shows, so the gallery can be
            // grouped instead of being one undifferentiated pile.
            $table->string('category', 32)->default('other')->after('caption');

            // Distinct from caption: a caption is editorial, alt text is what
            // a screen reader announces and what shows when an image fails.
            $table->string('alt_text', 255)->nullable()->after('category');

            // Exactly one per property. Enforced in the model rather than by a
            // partial unique index, which MySQL does not support.
            $table->boolean('is_cover')->default(false)->after('sort_order');

            $table->string('original_name', 255)->nullable();
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            // Lets the served image be shown not to have changed since upload,
            // the same guarantee member documents and ad snapshots carry.
            $table->char('sha256', 64)->nullable();

            $table->foreignId('uploaded_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            // No timestamps() here - the table already has them.

            // property_id + sort_order is already indexed by the original
            // migration; re-adding it fails outright rather than being ignored.
            $table->index(['property_id', 'is_cover']);
        });
    }

    public function down(): void
    {
        Schema::table('property_photos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('uploaded_by_user_id');
            $table->dropColumn([
                'disk', 'path', 'original_path', 'category', 'alt_text', 'is_cover',
                'original_name', 'mime_type', 'size_bytes', 'width', 'height', 'sha256',
            ]);
        });
    }
};
