<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A member's own number, and the listing URLs derived from it.
 *
 * Staff already identify members by a number when they talk to them. Until now
 * the site had no place to put it, so a listing's public address was its
 * database row id — a number that means nothing to anybody and quietly reveals
 * how many listings exist.
 *
 * `member_id` is typed in by staff rather than generated, because the number
 * already exists in whatever they used before this system and has to match it.
 *
 * `public_ref` is what a listing URL uses. It is derived from the owner's
 * member id — the first listing takes it bare, later ones get -2, -3 — and is
 * stored rather than computed on the fly so a URL cannot change underneath
 * somebody because a property was reordered.
 *
 * Both are nullable. Every existing account and listing predates this, and a
 * migration that demanded a value would have had to invent one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'member_id')) {
                // Unique, but nullable: two members must never share a number,
                // and MySQL permits many NULLs in a unique index.
                $table->string('member_id', 40)->nullable()->unique()->after('email');
            }
        });

        Schema::table('properties', function (Blueprint $table) {
            if (! Schema::hasColumn('properties', 'public_ref')) {
                $table->string('public_ref', 60)->nullable()->unique()->after('reference');
            }
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            if (Schema::hasColumn('properties', 'public_ref')) {
                $table->dropUnique(['public_ref']);
                $table->dropColumn('public_ref');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'member_id')) {
                $table->dropUnique(['member_id']);
                $table->dropColumn('member_id');
            }
        });
    }
};
