<?php

namespace App\Http\Controllers;

use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Support\EventCenters;
use Illuminate\View\View;

/**
 * Convention centers, and the advertisements near them.
 *
 * The reason this is a Vaytoven page rather than a bookmark folder: somebody
 * looking at a convention already knows their city and their dates. That is the
 * hardest part of matching a traveller to a listing, and they arrive with it
 * settled.
 *
 * So each center carries a live count of what Vaytoven actually advertises in
 * that city. A page promising to help find a place near McCormick Place while
 * having nothing in Chicago wastes the click, and the honest version — saying
 * how many are there, or that none are yet — is also the version that shows the
 * program working as listings arrive.
 */
class EventCenterController extends Controller
{
    public function index(): View
    {
        // One grouped query rather than five. The list is small enough that it
        // would not matter today, and structuring it per-center is how a page
        // quietly acquires a query per row.
        $counts = Property::query()
            ->where('status', PropertyStatus::Active->value)
            ->selectRaw('LOWER(city) as city_key, COUNT(*) as total')
            ->groupBy('city_key')
            ->pluck('total', 'city_key');

        $centers = EventCenters::all()->map(fn (array $center) => $center + [
            'listings' => (int) ($counts[strtolower($center['search']['city'])] ?? 0),
        ]);

        return view('event-centers.index', [
            'centers' => $centers,
        ]);
    }
}
