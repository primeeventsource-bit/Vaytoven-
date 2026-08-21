@extends('properties.layout')

@section('title', 'Stays')

@section('content')

    {{-- Vrbo-style search bar at the top, prefilled from the current query
         so iterating doesn't lose existing filters. --}}
    <div style="margin-bottom: 28px;">
        @include('partials.search-bar', [
            'compact'  => true,
            'defaults' => [
                'q'        => $q,
                'check_in' => request('check_in'),
                'check_out'=> request('check_out'),
                'adults'   => request('adults', 2),
                'children' => request('children', 0),
                'infants'  => request('infants', 0),
            ],
        ])
    </div>

    {{-- Active filter chips ------------------------------------------------- --}}
    @php
        $hasFilters = $destination || $q || $minCapacity || $minPrice || $maxPrice
            || ($selectedEventCenter ?? '') || count($selectedAmenities ?? []) > 0;
        $activeCenter = ($eventCenters ?? collect())->firstWhere('slug', $selectedEventCenter ?? '');
    @endphp
    @if ($hasFilters)
        <div class="props-active-filters">
            @if ($activeCenter)
                <span class="props-chip">{{ $activeCenter['label'] }}
                    <a href="{{ route('properties.index', request()->except(['event_center', 'page'])) }}">×</a>
                </span>
            @endif
            @if ($destination)
                <span class="props-chip">Destination: {{ ucwords(str_replace('-', ' ', $destination)) }} <a href="{{ route('properties.index') }}">×</a></span>
            @endif
            @if ($q)
                <span class="props-chip">Query: {{ $q }} <a href="{{ route('properties.index', request()->except(['q', 'page'])) }}">×</a></span>
            @endif
            @if ($minCapacity)
                <span class="props-chip">{{ $minCapacity }}+ guests <a href="{{ route('properties.index', request()->except(['min_capacity', 'page'])) }}">×</a></span>
            @endif
            @if ($minPrice || $maxPrice)
                <span class="props-chip">
                    @if ($minPrice && $maxPrice)
                        ${{ $minPrice }}–${{ $maxPrice }}
                    @elseif ($maxPrice)
                        Up to ${{ $maxPrice }}
                    @else
                        From ${{ $minPrice }}
                    @endif
                    <a href="{{ route('properties.index', request()->except(['min_price', 'max_price', 'page'])) }}">×</a>
                </span>
            @endif
            @foreach ($selectedAmenities ?? [] as $slug)
                @php
                    $matched = $filterAmenities->firstWhere('slug', $slug);
                    $remaining = collect($selectedAmenities)->reject(fn ($s) => $s === $slug)->values()->all();
                @endphp
                @if ($matched)
                    <span class="props-chip">{{ $matched->label }}
                        <a href="{{ route('properties.index', array_merge(request()->except(['amenities', 'page']), ['amenities' => $remaining])) }}">×</a>
                    </span>
                @endif
            @endforeach
        </div>
    @endif

    {{-- Filter rail (collapsible details on mobile, expanded inline on desktop) --}}
    <details class="props-filter-rail" {{ $hasFilters ? 'open' : '' }}>
        <summary>
            <span>Filters</span>
            <span class="props-filter-chevron">▾</span>
        </summary>
        <form method="GET" action="{{ route('properties.index') }}" class="props-filter-form">
            {{-- Carry over destination + q + capacity + dates so toggling filters
                 doesn't drop the rest of the search. --}}
            @if ($q)           <input type="hidden" name="q" value="{{ $q }}"> @endif
            @if ($destination) <input type="hidden" name="destination" value="{{ $destination }}"> @endif
            @foreach (['check_in','check_out','adults','children','infants','min_capacity'] as $passthrough)
                @if (request($passthrough))
                    <input type="hidden" name="{{ $passthrough }}" value="{{ request($passthrough) }}">
                @endif
            @endforeach

            {{-- Convention center areas.
                 A select rather than chips: these are mutually exclusive — a
                 property is not near McCormick Place AND the Javits Center —
                 and a row of checkboxes would invite a combination that can
                 only ever return nothing. --}}
            @if (($eventCenters ?? collect())->isNotEmpty())
                <div class="props-filter-section">
                    <h4>Event center area</h4>
                    <select name="event_center" class="props-filter-select" style="width:100%;padding:9px 10px;border:1px solid var(--line);border-radius:8px;font-size:14px;">
                        <option value="">Anywhere</option>
                        @foreach ($eventCenters as $center)
                            <option value="{{ $center['slug'] }}" @selected(($selectedEventCenter ?? '') === $center['slug'])>
                                {{ $center['label'] }}
                            </option>
                        @endforeach
                    </select>
                    <p style="font-size:12px;color:var(--muted);margin:8px 0 0;line-height:1.5;">
                        Shows advertisements in that convention center's city.
                        <a href="{{ route('event-centers.index') }}">See upcoming events →</a>
                    </p>
                </div>
            @endif

            <div class="props-filter-section">
                <h4>Price</h4>
                <div class="props-filter-price">
                    <label>
                        <span>Min $</span>
                        <input type="number" name="min_price" min="0" step="10" value="{{ $minPrice ?: '' }}" placeholder="Any">
                    </label>
                    <label>
                        <span>Max $</span>
                        <input type="number" name="max_price" min="0" step="10" value="{{ $maxPrice ?: '' }}" placeholder="Any">
                    </label>
                </div>
            </div>

            <div class="props-filter-section">
                <h4>Amenities</h4>
                <div class="props-filter-amenities">
                    @foreach ($filterAmenities as $a)
                        <label class="props-amenity-chip {{ in_array($a->slug, $selectedAmenities) ? 'is-on' : '' }}">
                            <input type="checkbox" name="amenities[]" value="{{ $a->slug }}" {{ in_array($a->slug, $selectedAmenities) ? 'checked' : '' }}>
                            {{ $a->label }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="props-filter-actions">
                <button type="submit" class="props-filter-apply">Apply filters</button>
                @if ($hasFilters)
                    <a href="{{ route('properties.index') }}" class="props-filter-clear">Clear all</a>
                @endif
            </div>
        </form>
    </details>

    {{-- Two-column layout: results list + sticky map ---------------------- --}}
    <div class="props-with-map">
        <div class="props-results-col">
            <div class="props-eyebrow">Vacation properties</div>
            <h1 class="props-title">
                @if ($destination)
                    Stays in {{ ucwords(str_replace('-', ' ', $destination)) }}
                @elseif ($q)
                    Results for "{{ $q }}"
                @else
                    Find your next stay
                @endif
            </h1>
            <p class="props-meta">{{ $properties->total() }} {{ Str::plural('property', $properties->total()) }} available</p>

            @if ($properties->isEmpty())
                <div class="props-empty">
                    <h3>No matches</h3>
                    <p>Try a broader search — fewer filters, or a different destination.</p>
                </div>
            @else
                <div class="props-grid">
                    @foreach ($properties as $property)
                        <a class="props-card"
                           href="{{ route('properties.show', $property) }}"
                           data-track-audience="traveler"
                           data-track-cta="property_card_open"
                           data-track-meta-id="{{ $property->id }}"
                           data-property-id="{{ $property->id }}">
                            @include('partials.photo-carousel', [
                                'photos' => $property->photos,
                                'alt'    => $property->title,
                            ])
                            <div class="props-card-body">
                                <h3>{{ $property->title }}</h3>
                                <div class="props-card-loc">{{ $property->city }}{{ $property->country ? ', '.$property->country : '' }}</div>
                                <div class="props-card-meta">
                                    {{ $property->capacity }} guests · {{ $property->bedrooms }} bed{{ $property->bedrooms === 1 ? '' : 's' }} · {{ rtrim(rtrim(number_format($property->bathrooms, 1), '0'), '.') }} bath
                                </div>
                                <div class="props-card-price">
                                    <span class="num">{{ $property->priceLabel() }}</span> {{ $property->priceCaption() }}
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="props-pagination">{{ $properties->links() }}</div>
            @endif
        </div>

        {{-- Map column — sticky on desktop, toggleable on mobile.
             Property pin data ships as a JSON island the map module reads. --}}
        @if ($properties->isNotEmpty())
            {{-- map.opened fires on the first click anywhere in this column.

                 On desktop the map is simply there, so "opened" can only mean
                 the visitor did something with it — dragged it, or clicked a
                 pin. On mobile the toggle below is inside the same element, so
                 tapping Show map counts too. The delegated listener in
                 vyt-track.js fires each element once per page, which is what
                 keeps a minute of panning from writing a hundred rows. --}}
            <aside class="props-map-col" data-vyt-map-col
                   data-vyt-event="map.opened" data-vyt-subject-type="search">
                <div class="props-map-sticky">
                    <div id="vyt-properties-map" class="vyt-leaflet-map" aria-label="Map of available properties"></div>
                </div>
                <button type="button" class="props-map-toggle" data-vyt-map-toggle aria-pressed="false">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 19l-5-2V5l5 2 6-2 5 2v12l-5 2-6-2z M9 5v14 M15 5v14"/></svg>
                    <span>Show map</span>
                </button>
            </aside>

            @php
                // Prepared outside the @json directive — Blade's directive
                // parser struggles to count parens through nested array literals.
                $mapPins = $properties->map(fn ($p) => [
                    'id'      => $p->id,
                    'title'   => $p->title,
                    'city'    => $p->city,
                    'country' => $p->country,
                    'lat'     => (float) $p->latitude,
                    'lng'     => (float) $p->longitude,
                    'price'   => (int) $p->price_cents,
                    // displayUrl(): an uploaded photo has a path and a null url.
                    'photo'   => $p->photos->first()?->displayUrl(),
                    'url'     => route('properties.show', $p),
                ])->values();
            @endphp
            <script id="vyt-map-data" type="application/json">@json($mapPins)</script>
        @endif
    </div>

@endsection
