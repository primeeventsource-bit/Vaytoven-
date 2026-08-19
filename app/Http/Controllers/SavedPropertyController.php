<?php

namespace App\Http\Controllers;

use App\Enums\ActivityType;
use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Models\Wishlist;
use App\Services\Tracking\ActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Saving an advertisement to come back to.
 *
 * The wishlist tables and the favorite.saved / favorite.removed activity types
 * have both been here since the booking product, and the listing analytics
 * screen has been reporting a saves count the whole time. Nothing could ever
 * write one: there was no button, no route and no controller, so the number was
 * structurally zero and read as "nobody is interested" rather than as "this is
 * not built".
 *
 * A member gets one list rather than the several the schema allows. Named lists
 * are a feature for someone comparing dozens of options; here the useful
 * question is only "which of these do I want to come back to", and offering an
 * empty list-management screen would be more product than the site has.
 */
class SavedPropertyController extends Controller
{
    /** Everything a member has saved, newest first. */
    public function index(Request $request): View
    {
        $wishlist = $this->listFor($request);

        $properties = $wishlist
            ->properties()
            ->with('photos')
            ->orderByDesc('wishlist_properties.added_at')
            ->get();

        return view('client.saved.index', [
            'properties' => $properties,
        ]);
    }

    /**
     * Save or unsave, in one route.
     *
     * A toggle rather than a store/destroy pair because the button has one
     * state and the member is pressing the same thing either way. It also means
     * a double-submit settles on a definite answer instead of erroring on a
     * duplicate insert.
     */
    public function toggle(Request $request, Property $property, ActivityRecorder $recorder): RedirectResponse
    {
        // A member can only save something that is actually advertised.
        // Otherwise a saved list quietly accumulates listings that were taken
        // down, and opening one 404s.
        abort_unless($property->status === PropertyStatus::Active, 404);

        $wishlist = $this->listFor($request);

        $existing = DB::table('wishlist_properties')
            ->where('wishlist_id', $wishlist->id)
            ->where('property_id', $property->id);

        $saved = $existing->exists();

        if ($saved) {
            $existing->delete();
        } else {
            $wishlist->properties()->attach($property->id, ['added_at' => now()]);
        }

        $recorder->record(
            $saved ? ActivityType::FavoriteRemoved : ActivityType::FavoriteSaved,
            $request,
            subjectType: 'property',
            subjectReference: $property->reference,
        );

        return back()->with('success', $saved ? 'Removed from your saved list.' : 'Saved.');
    }

    /**
     * The member's list, created on first use.
     *
     * firstOrCreate rather than a seeded row on registration, so members who
     * signed up before this existed get one the moment they press save.
     */
    private function listFor(Request $request): Wishlist
    {
        return Wishlist::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['name' => 'Saved', 'is_private' => true],
        );
    }
}
