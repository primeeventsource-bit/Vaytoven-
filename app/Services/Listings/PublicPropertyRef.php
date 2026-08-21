<?php

namespace App\Services\Listings;

use App\Models\Property;
use App\Models\User;

/**
 * Turns a member's number into the addresses their listings live at.
 *
 * The first listing takes the member id bare; the ones after it get -2, -3.
 * That keeps a URL readable and makes it obvious at a glance which member it
 * belongs to, which is the whole point of putting the number there.
 *
 * Refs are stored, not computed on request. A computed ref would change the
 * moment a listing was reordered or an earlier one was deleted, silently
 * breaking every link already sent to a client. Once assigned, a ref stays
 * with its listing.
 */
class PublicPropertyRef
{
    /**
     * Give every listing this member owns a public ref, keeping any it already
     * has.
     *
     * @return int how many listings were given a new ref
     */
    public function assignFor(User $member): int
    {
        $memberId = trim((string) $member->member_id);

        if ($memberId === '') {
            return 0;
        }

        // Oldest first, so the member's original listing is the one that gets
        // the bare number and keeps it.
        $listings = Property::where('host_id', $member->id)
            ->orderBy('id')
            ->get();

        $assigned = 0;
        $position = 0;

        foreach ($listings as $listing) {
            $position++;

            if ($listing->public_ref !== null) {
                // Already addressed. Renumbering it would break a live URL for
                // the sake of tidiness.
                continue;
            }

            $listing->forceFill(['public_ref' => $this->refFor($memberId, $position)])->save();
            $assigned++;
        }

        return $assigned;
    }

    /**
     * A ref for one listing, skipping anything already taken.
     *
     * The suffix walks forward rather than failing, because two members can be
     * given ids that collide with an existing ref — for instance member "20482"
     * arriving after somebody's listing already holds "20482-2".
     */
    public function refFor(string $memberId, int $position): string
    {
        $candidate = $position <= 1 ? $memberId : $memberId.'-'.$position;

        while (Property::where('public_ref', $candidate)->exists()) {
            $position++;
            $candidate = $memberId.'-'.$position;
        }

        return $candidate;
    }

    /** The ref a brand new listing for this member should take. */
    public function nextFor(User $member): ?string
    {
        $memberId = trim((string) $member->member_id);

        if ($memberId === '') {
            return null;
        }

        $existing = Property::where('host_id', $member->id)->whereNotNull('public_ref')->count();

        return $this->refFor($memberId, $existing + 1);
    }
}
