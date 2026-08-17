<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Force a password change on first sign-in.
 *
 * Any account created by staff — a new admin, a specialist, a member set up
 * over the phone — starts with a password somebody else chose and therefore
 * knows. Until the account holder replaces it, "the account did X" is not a
 * statement anyone can stand behind, because at least two people could have
 * done it.
 *
 * password_changed_at is recorded alongside so an operator can see how long an
 * account has been sitting on a staff-issued credential.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('password');
            $table->timestamp('password_changed_at')->nullable()->after('must_change_password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['must_change_password', 'password_changed_at']);
        });
    }
};
