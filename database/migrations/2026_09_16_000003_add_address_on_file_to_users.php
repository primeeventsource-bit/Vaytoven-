<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The member's address on file.
 *
 * Deliberately separate from every IP/GeoIP column in the system. This is what
 * the member (or staff, on their behalf) says their address is; GeoIP is an
 * approximate network location. The two are compared, never merged.
 *
 * address_latitude/longitude come from geocoding the address when it is saved
 * (US Census geocoder), so a login location can be compared by distance rather
 * than by guessing from city names.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'address_line1', 'address_line2', 'address_city', 'address_state',
        'address_postal_code', 'address_country',
        'address_latitude', 'address_longitude', 'address_geocoded_at',
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $column) {
            if (Schema::hasColumn('users', $column)) {
                continue;
            }

            Schema::table('users', function (Blueprint $table) use ($column) {
                match ($column) {
                    'address_line1', 'address_line2' => $table->string($column, 255)->nullable(),
                    'address_city'                   => $table->string($column, 128)->nullable(),
                    'address_state'                  => $table->string($column, 64)->nullable(),
                    'address_postal_code'            => $table->string($column, 20)->nullable(),
                    'address_country'                => $table->char($column, 2)->nullable(),
                    'address_latitude', 'address_longitude' => $table->decimal($column, 9, 6)->nullable(),
                    'address_geocoded_at'            => $table->timestamp($column)->nullable(),
                };
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(self::COLUMNS);
        });
    }
};
