<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * First name, last name, and phone on users.
 *
 * `name` is deliberately KEPT and kept authoritative for display. Dozens of
 * surfaces read it — chargeback certificates, admin tables, dashboards, PDF
 * evidence — and rewriting all of them to concatenate two columns would be a
 * lot of churn for no user-visible gain. The registration form now collects
 * the parts and composes `name` from them; the parts exist so the form can be
 * pre-filled and so "Hi Sarah" is possible without guessing where a name
 * splits.
 *
 * All three are nullable: ~70 accounts already exist with only a `name`, and
 * the API registration endpoint still takes a single `name`. The web form
 * requires them; the column does not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 120)->nullable()->after('name');
            $table->string('last_name', 120)->nullable()->after('first_name');
            // Loose length: E.164 is 15 digits but users type spaces,
            // brackets, and extensions. Stored as entered.
            $table->string('phone', 32)->nullable()->after('email');
        });

        $this->backfillNameParts();
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name', 'phone']);
        });
    }

    /**
     * Split existing `name` on the first space so the new columns are not
     * empty for everyone who registered before today. Deliberately naive —
     * "first token / the rest" is right far more often than any cleverer rule,
     * and `name` remains the authoritative display value either way, so a bad
     * split degrades a greeting rather than corrupting anything.
     */
    private function backfillNameParts(): void
    {
        DB::table('users')
            ->select('id', 'name')
            ->whereNull('first_name')
            ->orderBy('id')
            ->chunkById(500, function ($users) {
                foreach ($users as $user) {
                    $name = trim((string) $user->name);
                    if ($name === '') {
                        continue;
                    }

                    $parts = preg_split('/\s+/', $name, 2);

                    DB::table('users')->where('id', $user->id)->update([
                        'first_name' => $parts[0],
                        'last_name' => $parts[1] ?? null,
                    ]);
                }
            });
    }
};
