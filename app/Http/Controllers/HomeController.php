<?php

namespace App\Http\Controllers;

use App\Enums\PropertyStatus;
use App\Models\Property;
use Illuminate\View\View;

/**
 * The public homepage.
 *
 * It was a static view, and its "featured stays" were four invented listings
 * — places Vaytoven does not advertise, with invented ratings and dates, and
 * no link to anything. The section now shows the real advertisements, newest
 * first, which is also the order Stays uses.
 */
class HomeController extends Controller
{
    /** How many advertisements the featured row holds. */
    private const FEATURED = 4;

    public function show(): View
    {
        return view('welcome', [
            'featured' => Property::query()
                ->where('status', PropertyStatus::Active->value)
                // A card with no picture reads as a broken page, so a listing
                // without one waits for its photos rather than leading the
                // homepage with an empty box.
                ->whereHas('photos')
                ->with(['photos' => fn ($q) => $q->orderBy('sort_order')])
                // No rating is carried. The old cards wore invented stars;
                // a real one is impossible today — reviews hang off bookings,
                // which this company retired, so the table is empty and
                // nothing can write to it. Better nothing than decoration.
                ->orderByRaw('published_at is null')
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->limit(self::FEATURED)
                ->get(),
        ]);
    }
}
