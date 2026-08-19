<?php

namespace App\Http\Controllers;

use App\Enums\PropertyStatus;
use App\Enums\ActivityType;
use App\Models\Amenity;
use App\Models\Property;
use App\Models\PropertyView;
use App\Services\GeoIp\GeoIpService;
use App\Services\Tracking\ActivityRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * Public web surface for browsing and viewing properties.
 *
 * Distinct from Api\PropertyController which serves JSON to the SDK and
 * future mobile clients. This controller renders the branded marketing-grade
 * Blade views for visitors arriving from the landing's destination cards
 * and search form.
 *
 * Search filters mirror the API to keep server logic consistent:
 *   - q              free-text on title/city/region
 *   - city, country  exact match
 *   - destination    convenience alias for city (used by destination cards)
 *   - min_capacity, max_price (whole dollars, converted to cents internally)
 *
 * Only `active`-status properties are visible. Inactive/draft listings 404.
 */
class PropertyBrowseController extends Controller
{
    public function index(Request $request): View
    {
        $query = Property::query()
            ->where('status', PropertyStatus::Active->value)
            ->with(['photos' => fn ($q) => $q->orderBy('sort_order')]);

        if ($q = trim((string) $request->query('q', ''))) {
            // The TERM is recorded, not the visitor. It answers "what are
            // people looking for that we do not advertise", which is the
            // only reason to keep a search at all.
            app(ActivityRecorder::class)->record(
                ActivityType::SearchPerformed,
                $request,
                result: 'successful',
                metadata: ['term' => mb_substr($q, 0, 120)],
            );

            $query->where(function ($w) use ($q) {
                $w->where('title', 'like', "%{$q}%")
                    ->orWhere('city', 'like', "%{$q}%")
                    ->orWhere('region', 'like', "%{$q}%");
            });
        }

        // 'destination' is the alias the landing destination cards use.
        // It maps to a relaxed city match (handles slugs like 'lake-tahoe').
        if ($destination = trim((string) $request->query('destination', ''))) {
            $needle = str_replace('-', ' ', strtolower($destination));
            $query->whereRaw('LOWER(city) LIKE ?', ["%{$needle}%"]);
        }

        if ($city = trim((string) $request->query('city', ''))) {
            $query->where('city', $city);
        }

        if ($country = trim((string) $request->query('country', ''))) {
            $query->where('country', strtoupper($country));
        }

        // The Vrbo-style search bar submits adults + children + infants
        // separately. Roll them up into a capacity floor (infants typically
        // don't count toward a property's stated capacity).
        $adults = (int) $request->integer('adults', 0);
        $children = (int) $request->integer('children', 0);
        $totalGuests = $adults + $children;
        $minCapacity = max((int) $request->integer('min_capacity', 0), $totalGuests);

        if ($minCapacity > 0) {
            $query->where('capacity', '>=', $minCapacity);
        }

        if ($request->filled('min_price')) {
            $query->where('base_nightly_cents', '>=', (int) $request->integer('min_price') * 100);
        }
        if ($request->filled('max_price')) {
            // UI passes whole dollars; storage is integer cents.
            $query->where('base_nightly_cents', '<=', (int) $request->integer('max_price') * 100);
        }

        // Amenity multi-select. Vrbo-style: every checked amenity must be
        // present on the listing (AND, not OR), so a 'pool + wifi' search
        // only returns properties carrying both.
        $amenitySlugs = collect((array) $request->query('amenities', []))
            ->filter(fn ($s) => is_string($s) && $s !== '')
            ->unique()
            ->values();
        if ($amenitySlugs->isNotEmpty()) {
            $amenityIds = Amenity::query()
                ->whereIn('slug', $amenitySlugs)
                ->pluck('id');

            // If the user asked for amenities but none of them resolve to a
            // known row, force zero results — silently dropping the filter
            // would leak listings the user never asked to see.
            if ($amenityIds->isEmpty()) {
                $query->whereRaw('1=0');
            } else {
                foreach ($amenityIds as $amenityId) {
                    // whereHas per amenity gives AND-of-required semantics —
                    // simplest correct path that survives any pivot row count.
                    $query->whereHas('amenities', fn ($q) => $q->where('amenities.id', $amenityId));
                }
            }
        }

        $properties = $query
            ->orderBy('base_nightly_cents')
            ->paginate(12)
            ->withQueryString();

        // Curated filter chips for the sidebar — small, recognisable set
        // rather than the full 50+ amenity catalogue. Editorial choice.
        $filterAmenities = Amenity::query()
            ->whereIn('slug', [
                'wifi', 'pool', 'hot-tub', 'pets-allowed', 'beachfront',
                'kitchen', 'air-conditioning', 'free-parking', 'fireplace',
                'fast-wifi',
            ])
            ->orderBy('label')
            ->get(['id', 'slug', 'label']);

        return view('properties.index', [
            'properties'      => $properties,
            'q'               => $q ?? '',
            'destination'     => $destination ?? '',
            'minCapacity'     => $request->integer('min_capacity'),
            'minPrice'        => $request->integer('min_price'),
            'maxPrice'        => $request->integer('max_price'),
            'filterAmenities' => $filterAmenities,
            'selectedAmenities' => $amenitySlugs->all(),
        ]);
    }

    public function show(Property $property, Request $request, GeoIpService $geoIp): View
    {
        // A listing that is not live is invisible to the public, but visible to
        // the people who need to check it BEFORE it goes live. Previewing a
        // draft is the whole point of previewing: by the time it is active it
        // is too late to find the typo.
        if ($property->status !== PropertyStatus::Active) {
            $viewer = $request->user();

            abort_unless(
                $viewer && ($viewer->isStaff() || $property->host_id === $viewer->id),
                404,
            );

            // Marked so the page can say so. A preview that looks identical to
            // the live page is how someone concludes a draft is published.
            $request->attributes->set('vyt_preview', true);
        }

        $property->load(['amenities', 'photos' => fn ($q) => $q->orderBy('sort_order'), 'host:id,name,first_name,last_name']);

        if (! $request->attributes->get('vyt_preview')) {
            // Staff checking their own work is not a visitor. Counting it
            // would inflate the member's view count and put an internal
            // action in the visitor journey.
            $this->recordView($property, $request, $geoIp);

        }

        // The audit log's own record of the same moment. property_views
        // powers the member's analytics; this is the staff-side trail that
        // carries IP, session and device alongside it.
        if (! $request->attributes->get('vyt_preview')) {
        app(ActivityRecorder::class)->record(
            ActivityType::PropertyViewed,
            $request,
            subjectType: 'property',
            subjectReference: $property->reference,
            result: 'successful',
        );
        }

        $response = view('properties.show', [
            'property' => $property,
        ]);

        // Ensure the visitor_id cookie persists across visits.
        if (! $request->cookie('vyt_visitor')) {
            cookie()->queue(cookie()->forever('vyt_visitor', $request->attributes->get('vyt_visitor_id') ?? (string) Str::uuid()));
        }

        return $response;
    }

    /**
     * Write a property_views row for the current request, deduped within a
     * 30-minute window per (property_id, visitor_id) so a refresh doesn't
     * inflate the count. Wrapped in try/catch — analytics must never break
     * page rendering (FR-10.5, same rule as login tracking).
     */
    private function recordView(Property $property, Request $request, GeoIpService $geoIp): void
    {
        try {
            $visitorId = $request->cookie('vyt_visitor');
            if (! $visitorId) {
                $visitorId = (string) Str::uuid();
                $request->attributes->set('vyt_visitor_id', $visitorId);
            }

            $recent = PropertyView::query()
                ->where('property_id', $property->id)
                ->where('visitor_id', $visitorId)
                ->where('occurred_at', '>=', now()->subMinutes(30))
                ->exists();
            if ($recent) {
                return;
            }

            $geo = $geoIp->lookup($request->ip());

            PropertyView::create([
                'property_id'    => $property->id,
                'viewer_user_id' => $request->user()?->id,
                'visitor_id'     => $visitorId,
                'ip_address'     => $request->ip(),
                'country'        => $geo->country,
                'region'         => $geo->region,
                'city'           => $geo->city,
                'latitude'       => $geo->latitude,
                'longitude'      => $geo->longitude,
                'user_agent'     => substr((string) $request->userAgent(), 0, 512),
                'occurred_at'    => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
            // Never rethrow — tracking failures must not break the page.
        }
    }
}
