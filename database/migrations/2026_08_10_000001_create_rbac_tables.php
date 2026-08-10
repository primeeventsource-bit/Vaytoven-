<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Role-Based Access Control (FR-1.4).
 *
 * Layered ON TOP of the existing `users.role` enum column rather than
 * replacing it: that column stays the user's primary role and everything
 * already keyed off it (dashboards, feature-flag scoping, EnsureAdmin) keeps
 * working untouched. RBAC resolves a user's effective permissions from the
 * system role whose `key` matches that enum value, UNION any roles attached
 * through `role_user`. So an existing admin gains the seeded `admin` role's
 * permissions with no data backfill.
 *
 * `permissions` is a synced mirror of App\Support\PermissionCatalog — the
 * catalog is the source of truth, this table exists so roles can foreign-key
 * to it and so the admin UI can join.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            // e.g. 'properties.publish'. Matches PermissionCatalog::PERMISSIONS.
            $table->string('key', 128)->unique();
            $table->string('module', 64)->index();
            $table->string('label', 128);
            $table->string('description', 512)->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            // Slug. For the six built-ins this equals the UserRole enum value,
            // which is how a user's primary role resolves to a permission set.
            $table->string('key', 64)->unique();
            $table->string('name', 128);
            $table->string('description', 512)->nullable();
            // System roles are seeded, cannot be deleted, and their key is
            // immutable — custom roles created by a super admin are neither.
            $table->boolean('is_system')->default(false)->index();
            // Super-admin roles bypass every permission check.
            $table->boolean('is_super')->default(false);
            // Higher level = more privileged. Used to stop a role holder from
            // editing or assigning a role at or above their own level.
            $table->unsignedSmallInteger('level')->default(0)->index();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->unique(['role_id', 'permission_id'], 'permission_role_unique');
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->unique(['user_id', 'role_id'], 'role_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};
