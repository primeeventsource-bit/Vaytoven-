<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Enable PostGIS for geographic search
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
            DB::statement('CREATE EXTENSION IF NOT EXISTS pgcrypto');
            DB::statement('CREATE EXTENSION IF NOT EXISTS citext');
        }

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('name', 80);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // PostgreSQL: use citext for case-insensitive emails
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('phone', 32)->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('password_hash');
            $table->string('first_name', 80)->nullable();
            $table->string('last_name', 80)->nullable();
            $table->string('display_name', 120)->nullable();
            $table->text('avatar_url')->nullable();
            $table->text('bio')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('locale', 10)->default('en');
            $table->char('currency', 3)->default('USD');
            $table->boolean('is_host')->default(false);
            $table->boolean('is_superhost')->default(false);
            $table->timestamp('govt_id_verified_at')->nullable();
            $table->string('govt_id_verification_id', 120)->nullable();
            $table->boolean('two_factor_enabled')->default(false);
            $table->string('two_factor_secret')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->ipAddress('last_login_ip')->nullable();
            $table->unsignedInteger('failed_login_count')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('privacy_consent_at')->nullable();
            $table->boolean('marketing_opt_in')->default(false);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_host');
            $table->index('status');
            $table->index('deleted_at');
        });

        // Add CHECK constraint for status (Postgres only — MySQL uses ENUM differently)
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check
                CHECK (status IN ('active','suspended','banned','pending_verification'))");
        }

        Schema::create('user_roles', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->timestamp('granted_at')->useCurrent();
            $table->foreignId('granted_by')->nullable()->constrained('users');
            $table->primary(['user_id', 'role_id']);
        });

        Schema::create('user_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash')->unique();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_label', 120)->nullable();
            $table->timestamp('last_active_at')->useCurrent();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason', 60)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'last_active_at']);
        });

        // Seed roles
        DB::table('roles')->insert([
            ['slug' => 'guest',       'name' => 'Guest',       'description' => 'Searches and books stays.', 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'host',        'name' => 'Host',        'description' => 'Lists and manages properties.', 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'admin',       'name' => 'Admin',       'description' => 'Platform operations.', 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'super_admin', 'name' => 'Super Admin', 'description' => 'Full system access.', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');
    }
};
