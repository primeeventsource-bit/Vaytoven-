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
        $hasFilters = $destination || $q || $minCapacity || $minPrice || $maxPrice || count($selectedAmenities ?? []) > 0;
    @endphp
    @if ($hasFilters)
        <div class="props-active-filters">
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
                        ${{ $minPrice }}–${{ $maxPrice }}/night
                    @elseif ($maxPrice)
                        Up to ${{ $maxPrice }}/night
                    @else
                        From ${{ $minPrice }}/night
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

            <div class="props-filter-section">
                <h4>Price per night</h4>
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
                            @if ($property->photos->first())
                                <img class="props-card-img" src="{{ $property->photos->first()->url }}" alt="{{ $property->title }}" loading="lazy">
                            @else
                                <div class="props-card-img" aria-hidden="true"></div>
                            @endif
                            <div class="props-card-body">
                                <h3>{{ $property->title }}</h3>
                                <div class="props-card-loc">{{ $property->city }}{{ $property->country ? ', '.$property->country : '' }}</div>
                                <div class="props-card-meta">
                                    {{ $property->capacity }} guests · {{ $property->bedrooms }} bed{{ $property->bedrooms === 1 ? '' : 's' }} · {{ rtrim(rtrim(number_format($property->bathrooms, 1), '0'), '.') }} bath
                                </div>
                                <div class="props-card-price">
                                    <span class="num">${{ number_format($property->base_nightly_cents / 100) }}</span> / night
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
            <aside class="props-map-col" data-vyt-map-col>
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
                    'price'   => (int) $p->base_nightly_cents,
                    'photo'   => $p->photos->first()?->url,
                    'url'     => route('properties.show', $p),
                ])->values();
            @endphp
            <script id="vyt-map-data" type="application/json">@json($mapPins)</script>
        @endif
    </div>

@endsection
