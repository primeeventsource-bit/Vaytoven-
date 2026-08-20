<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A shared photo library, independent of any one listing.
 *
 * Property photos are attached to the property that owns them, which is right
 * for a photo OF that property and wrong for the stock set staff keep on hand.
 * Re-uploading the same pool shot for every new member is slow, produces a
 * fresh copy in the bucket each time, and means nobody can find "the good
 * exterior ones" without opening listings until they turn up.
 *
 * So assets live here once, in named collections, and are COPIED onto a
 * listing when used. Copying rather than referencing is deliberate: a live
 * advertisement must not change or break because somebody tidied the library,
 * and each listing needs its own caption, alt text, ordering and cover flag
 * for the same underlying image.
 *
 * Index names are given explicitly. An auto-generated name on these column
 * combinations runs past MySQL's 64-character limit, which is fine on SQLite
 * locally and fatal on deploy - this project has been bitten by exactly that.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_collections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();

            // Nullable: an upload that has not been filed yet still has to be
            // findable rather than dropped, so unfiled assets are a real state.
            $table->foreignId('media_collection_id')->nullable()
                ->constrained('media_collections')->nullOnDelete();

            $table->string('disk', 64);
            $table->string('path');
            $table->string('original_path')->nullable();

            $table->string('original_name')->nullable();
            $table->string('mime_type', 64)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            // Content hash. Lets the same stock photo be recognised on a second
            // upload instead of quietly becoming a second copy in the bucket.
            $table->string('sha256', 64)->nullable();

            $table->string('label')->nullable();
            $table->string('alt_text')->nullable();

            $table->foreignId('uploaded_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['media_collection_id', 'created_at'], 'media_assets_coll_created_idx');
            $table->index('sha256', 'media_assets_sha_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
        Schema::dropIfExists('media_collections');
    }
};
