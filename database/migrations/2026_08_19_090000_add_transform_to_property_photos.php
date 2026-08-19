<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rotation and crop, stored as instructions rather than baked in.
 *
 * The served image could simply be overwritten with a rotated copy, but every
 * edit would then re-encode an already-lossy WebP, and a member who cropped too
 * tightly could never get the edge back. Keeping the numbers means the
 * derivative is always one transform away from the pristine original, so
 * repeated edits cost nothing in quality and every one of them is reversible.
 *
 * The crop is stored as fractions of the original rather than pixels so it
 * survives MAX_EDGE changing, and so the editor can draw the current box over a
 * thumbnail of any size without arithmetic.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_photos', function (Blueprint $table) {
            if (! Schema::hasColumn('property_photos', 'rotation')) {
                $table->smallInteger('rotation')->default(0)->after('height');
            }

            foreach (['crop_x', 'crop_y', 'crop_w', 'crop_h'] as $column) {
                if (! Schema::hasColumn('property_photos', $column)) {
                    $table->decimal($column, 6, 5)->nullable()->after('rotation');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('property_photos', function (Blueprint $table) {
            foreach (['rotation', 'crop_x', 'crop_y', 'crop_w', 'crop_h'] as $column) {
                if (Schema::hasColumn('property_photos', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
