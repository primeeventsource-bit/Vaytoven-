<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columns the admin user-management UI needs:
 *
 *   - deactivated_at         : when the user was suspended (null = active).
 *                              Soft-deactivation only; we never hard-delete
 *                              user rows because chargeback / login / booking
 *                              history must survive.
 *   - deactivated_by_user_id : who pulled the trigger (audit trail beyond
 *                              admin_audit_logs).
 *   - created_by_user_id     : null for self-signup, populated for users an
 *                              admin manually created via the user-mgmt UI.
 *   - last_login_at          : fast-query "last seen" for the user table.
 *                              login_sessions / tracking_events still own
 *                              the full audit; this is just a cache so the
 *                              user list doesn't N+1 into login_sessions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('deactivated_at')->nullable()->after('email_verified_at');
            $table->foreignId('deactivated_by_user_id')->nullable()->after('deactivated_at')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->after('deactivated_by_user_id')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('last_login_at')->nullable()->after('created_by_user_id');

            $table->index('deactivated_at');
            $table->index('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['deactivated_at']);
            $table->dropIndex(['last_login_at']);
            $table->dropConstrainedForeignId('deactivated_by_user_id');
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropColumn(['deactivated_at', 'last_login_at']);
        });
    }
};
