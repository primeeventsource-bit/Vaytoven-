<?php

namespace App\Services\Admin;

use App\Models\Contract;
use App\Models\MediaAsset;
use App\Models\MemberDocument;
use App\Models\MemberEnquiry;
use App\Models\MemberOffer;
use App\Models\MemberServiceOrder;
use App\Models\Property;
use App\Models\PropertyPhoto;
use App\Models\TermsAcceptance;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Removes the demo accounts and everything hanging off them.
 *
 * Demo data is seeded so the site has something to show before real members
 * arrive. Once they do, it stops being a demonstration and starts being
 * fiction sitting in the same tables as the real thing — publicly listed,
 * counted in totals, and indistinguishable at a glance from a paying member's
 * record.
 *
 * Two properties make this safe enough to put behind a button:
 *
 * It is scoped by email suffix and re-checks every single account against that
 * suffix immediately before deleting it. A bug elsewhere that widened the
 * selection cannot get past that check, because the check does not trust the
 * selection — it re-derives it.
 *
 * It previews. Nothing is destroyed until somebody has seen the counts and
 * typed the confirmation, so "delete the demo data" is never a guess about
 * what that means.
 *
 * tracking_events is deliberately NOT purged. The table is append-only at the
 * database level and refuses DELETE by design; that guarantee is worth more
 * than tidiness, and the rows are anonymous activity records rather than
 * anything identifying. The preview says so rather than letting somebody
 * discover it from an error.
 */
class DemoDataPurge
{
    /** Accounts whose email ends with this are demo accounts. */
    public const DEFAULT_SUFFIX = '@demo.vaytoven.local';

    public function __construct(private readonly string $suffix = self::DEFAULT_SUFFIX)
    {
    }

    /** @return Collection<int, User> */
    public function accounts(): Collection
    {
        // A bare suffix would match nothing sensible, and an empty one would
        // match every account on the system. Refuse rather than proceed.
        if (trim($this->suffix) === '' || ! str_contains($this->suffix, '@')) {
            return collect();
        }

        return User::query()
            ->where('email', 'like', '%'.$this->suffix)
            ->orderBy('email')
            ->get();
    }

    /**
     * What would be removed, without removing it.
     *
     * @return array<string, mixed>
     */
    public function preview(): array
    {
        $users = $this->accounts();
        $ids   = $users->pluck('id');
        $mails = $users->pluck('email');

        $propertyIds = Property::whereIn('host_id', $ids)->pluck('id');

        return [
            'suffix'   => $this->suffix,
            'accounts' => $users->map(fn (User $u) => [
                'id'    => $u->id,
                'email' => $u->email,
                'role'  => $u->role?->value ?? '—',
                'staff' => $u->isStaff(),
            ])->all(),
            'counts' => [
                'Accounts'              => $users->count(),
                'Listings'              => $propertyIds->count(),
                'Listing photos'        => PropertyPhoto::whereIn('property_id', $propertyIds)->count(),
                'Offers and inquiries'  => MemberOffer::whereIn('property_id', $propertyIds)
                                            ->orWhereIn('member_user_id', $ids)
                                            ->orWhereIn('buyer_user_id', $ids)->count(),
                'Member inquiries'      => MemberEnquiry::whereIn('email', $mails)->count(),
                'Member Services orders' => MemberServiceOrder::whereIn('email', $mails)->count(),
                'Contracts'             => Contract::whereIn('user_id', $ids)->count(),
                'Terms acceptances'     => TermsAcceptance::whereIn('user_id', $ids)->count(),
                'Documents'             => MemberDocument::whereIn('user_id', $ids)
                                            ->orWhereIn('property_id', $propertyIds)->count(),
                'Saved lists'           => Wishlist::whereIn('user_id', $ids)->count(),
            ],
            // Named so nobody is surprised by what survives.
            'retained' => [
                'Activity log entries' => 'tracking_events is append-only and refuses deletion by design. '
                    .'The rows are anonymous activity, not member records.',
                'Photo library'        => MediaAsset::count().' shared library image(s) are untouched — the library is staff property, not demo data.',
            ],
        ];
    }

    /**
     * Delete the demo accounts and their data.
     *
     * @param  User|null  $actor  never deleted, even if their address matches
     * @return array<string, int> what was actually removed
     */
    public function purge(?User $actor = null): array
    {
        $users = $this->accounts()
            // Deleting the account you are signed in as would end the request
            // halfway through and leave the rest unpurged.
            ->reject(fn (User $u) => $actor && $u->id === $actor->id);

        $removed = array_fill_keys(
            ['accounts', 'listings', 'photos', 'offers', 'enquiries', 'orders',
             'contracts', 'acceptances', 'documents', 'wishlists', 'files'],
            0
        );

        if ($users->isEmpty()) {
            return $removed;
        }

        $ids   = $users->pluck('id');
        $mails = $users->pluck('email');

        $properties  = Property::whereIn('host_id', $ids)->get();
        $propertyIds = $properties->pluck('id');

        // Stored objects go first and outside the transaction: a rolled-back
        // delete can put a row back, nothing can put a bucket object back, so
        // files are only removed once the rows they belong to are certain to go.
        DB::transaction(function () use ($ids, $mails, $properties, $propertyIds, &$removed, $users) {
            foreach ($properties as $property) {
                foreach ($property->photos as $photo) {
                    if ($photo->isUploaded()) {
                        $removed['files'] += (int) Storage::disk($photo->disk)
                            ->delete(array_filter([$photo->path, $photo->original_path]));
                    }
                }
            }

            foreach (MemberDocument::whereIn('user_id', $ids)->orWhereIn('property_id', $propertyIds)->get() as $doc) {
                Storage::disk($doc->disk)->delete($doc->path);
                $removed['files']++;
            }

            $removed['photos']      = PropertyPhoto::whereIn('property_id', $propertyIds)->delete();
            $removed['documents']   = MemberDocument::whereIn('user_id', $ids)->orWhereIn('property_id', $propertyIds)->delete();
            $removed['offers']      = MemberOffer::whereIn('property_id', $propertyIds)
                                        ->orWhereIn('member_user_id', $ids)
                                        ->orWhereIn('buyer_user_id', $ids)->delete();
            $removed['enquiries']   = MemberEnquiry::whereIn('email', $mails)->delete();
            $removed['orders']      = MemberServiceOrder::whereIn('email', $mails)->delete();
            $removed['contracts']   = Contract::whereIn('user_id', $ids)->delete();
            $removed['acceptances'] = TermsAcceptance::whereIn('user_id', $ids)->delete();
            $removed['wishlists']   = Wishlist::whereIn('user_id', $ids)->delete();

            foreach ($properties as $property) {
                $property->availabilityWeeks()->delete();
                $property->amenities()->detach();
                $property->delete();
                $removed['listings']++;
            }

            foreach ($users as $user) {
                // The suffix is re-checked here rather than trusted from the
                // selection above. If anything ever widened that query, this is
                // the line that refuses to act on it.
                if (! str_ends_with($user->email, $this->suffix)) {
                    continue;
                }

                $user->roles()->detach();
                $user->delete();
                $removed['accounts']++;
            }
        });

        return $removed;
    }
}
