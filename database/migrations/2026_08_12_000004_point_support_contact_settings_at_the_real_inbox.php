<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repoint the seeded contact defaults at the company's real inbox.
 *
 * SettingsSeeder writes each row once with firstOrCreate and never updates it,
 * which is the correct production behaviour — an operator's edit must survive a
 * re-seed. The side effect is that changing a default in SettingsSchema does
 * nothing to environments that were already seeded: `general.support_email`
 * still carries default_value 'support@vaytoven.com', a mailbox that does not
 * exist and that the admin console presents as the fallback.
 *
 * Only rows still holding the old placeholder are touched, and only when the
 * operator has not set a value of their own, so a deliberate choice is never
 * overwritten.
 */
return new class extends Migration
{
    private const FIXES = [
        'general.support_email' => ['support@vaytoven.com', 'contact@vaytoven.com'],
        'general.support_phone' => ['', '(877) 782-9868'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        foreach (self::FIXES as $key => [$old, $new]) {
            DB::table('settings')
                ->where('key', $key)
                ->where('default_value', $old)
                ->update(['default_value' => $new, 'updated_at' => now()]);

            // An operator who explicitly typed the dead address gets corrected
            // too — it was never a real destination, so leaving it is not
            // respecting a preference, it is preserving a bounce.
            if ($old !== '') {
                DB::table('settings')
                    ->where('key', $key)
                    ->where('value', $old)
                    ->update(['value' => $new, 'updated_at' => now()]);
            }

            Cache::forget("settings:{$key}");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        foreach (self::FIXES as $key => [$old, $new]) {
            DB::table('settings')
                ->where('key', $key)
                ->where('default_value', $new)
                ->update(['default_value' => $old, 'updated_at' => now()]);

            Cache::forget("settings:{$key}");
        }
    }
};
