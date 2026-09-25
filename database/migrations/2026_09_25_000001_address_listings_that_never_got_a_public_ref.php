<?php

use App\Models\Property;
use App\Models\User;
use App\Services\Listings\PublicPropertyRef;
use Illuminate\Database\Migrations\Migration;

/**
 * Give every listing its own published address.
 *
 * A listing only received a public_ref if its owner already had a member
 * number when the listing was created, and adding the number afterwards only
 * backfilled from the user-edit screen. Listings that missed both fell back
 * to printing the MEMBER's number as their "Property ID" — the same value on
 * every listing that member owns, and an address that 404s.
 *
 * Refs that already exist are kept: renumbering one would break a URL that
 * has already been sent to a client.
 */
return new class extends Migration
{
    public function up(): void
    {
        $owners = Property::query()
            ->whereNull('public_ref')
            ->whereNotNull('host_id')
            ->distinct()
            ->pluck('host_id');

        User::query()
            ->whereIn('id', $owners)
            ->whereNotNull('member_id')
            ->where('member_id', '!=', '')
            ->each(fn (User $member) => app(PublicPropertyRef::class)->assignFor($member));
    }

    public function down(): void
    {
        // Addresses handed out are not taken back.
    }
};
