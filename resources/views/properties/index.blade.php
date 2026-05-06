@extends('properties.layout')

@section('title', 'Stays')

@section('content')

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

    {{-- Filter form ----------------------------------------------------- --}}
    <form class="props-filters" method="GET" action="{{ route('properties.index') }}">
        <div class="props-filter">
            <label for="f-q">Search</label>
            <input id="f-q" type="text" name="q" value="{{ $q }}" placeholder="Bali, Paris, beachfront…" autocomplete="off">
        </div>
        <div class="props-filter">
            <label for="f-cap">Guests</label>
            <input id="f-cap" type="number" name="min_capacity" value="{{ $minCapacity ?: '' }}" min="1" max="20">
        </div>
        <div class="props-filter">
            <label for="f-price">Max nightly $</label>
            <input id="f-price" type="number" name="max_price" value="{{ $maxPrice ?: '' }}" min="0" step="10">
        </div>
        @if ($destination)
            <input type="hidden" name="destination" value="{{ $destination }}">
        @endif
        <button type="submit" class="props-filter-submit">Search</button>
    </form>

    {{-- Active filter chips -------------------------------------------- --}}
    @if ($destination || $q || $minCapacity || $maxPrice)
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
            @if ($maxPrice)
                <span class="props-chip">Up to ${{ $maxPrice }}/night <a href="{{ route('properties.index', request()->except(['max_price', 'page'])) }}">×</a></span>
            @endif
        </div>
    @endif

    {{-- Property grid -------------------------------------------------- --}}
    @if ($properties->isEmpty())
        <div class="props-empty">
            <h3>No matches</h3>
            <p>Try a broader search — fewer filters, or a different destination.</p>
        </div>
    @else
        <div class="props-grid">
            @foreach ($properties as $property)
                <a class="props-card" href="{{ route('properties.show', $property) }}" data-track-audience="traveler" data-track-cta="property_card_open" data-track-meta-id="{{ $property->id }}">
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

@endsection
