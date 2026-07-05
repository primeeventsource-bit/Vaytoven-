<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin Settings & Configuration subsystem — Layer 1 (typed key/value
 * settings) and Layer 3 (feature flags).
 *
 * The allow-list of valid keys, their types, validation rules, labels and
 * defaults lives in code (App\Services\Settings\SettingsSchema) — these rows
 * hold the operator-set values. `type='encrypted'` values are encrypted at
 * rest via Crypt; `is_sensitive` values are redacted to '••••' in audit
 * payloads and API/UI responses.
 *
 * Index names are explicit and short (MySQL 64-char identifier limit — same
 * convention as dev_exchange_mappings_unique).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 64);
            $table->string('key', 128)->unique('settings_key_unique');
            $table->string('type', 24)->default('string');
            $table->text('value')->nullable();
            $table->text('default_value')->nullable();
            $table->json('enum_options')->nullable();
            $table->boolean('is_public')->default(false);
            $table->boolean('is_sensitive')->default(false);
            $table->string('label', 160);
            $table->text('help_text')->nullable();
            $table->integer('sort_order')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('group', 'settings_group_idx');
            $table->index('is_public', 'settings_is_public_idx');
        });

        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();
            $table->string('key', 128)->unique('feature_flags_key_unique');
            $table->boolean('enabled')->default(true);
            // 'global' | 'role' | 'audience' | 'environment'
            $table->string('scope', 24)->default('global');
            $table->string('scope_value', 64)->nullable();
            $table->text('description')->nullable();
            // Optional gradual rollout 0..100; null = 100%.
            $table->unsignedSmallInteger('rollout_pct')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_flags');
        Schema::dropIfExists('settings');
    }
};
