@extends('layouts.site')

@section('title', 'Destinations')
@section('meta_description', 'Browse Vaytoven destinations. Every city listed has properties available to book right now.')

@push('head')
    <style>
        .dest-search { display:flex; gap:10px; margin: 0 0 28px; flex-wrap:wrap; }
        .dest-search input {
            flex:1; min-width:240px; padding:12px 15px; border:1px solid var(--line);
            border-radius:999px; font:inherit; font-size:15px; background:#fff; outline:none;
        }
        .dest-search input:focus { border-color:var(--magenta); box-shadow:0 0 0 3px rgba(255,61,138,.14); }
        .dest-card {
            display:block; text-decoration:none; color:inherit; overflow:hidden;
            border:1px solid var(--line); border-radius:14px; background:#fff;
            transition:transform .14s, box-shadow .14s;
        }
        .dest-card:hover { transform:translateY(-2px); box-shadow:0 18px 40px -24px rgba(123,44,191,.35); text-decoration:none; }
        .dest-card-img { height:170px; background:linear-gradient(135deg,#FF3D8A,#7B2CBF); background-size:cover; background-position:center; }
        .dest-card-body { padding:16px 18px; }
        .dest-card h3 { margin:0 0 4px; font-size:17px; }
        .dest-card .meta { font-size:13px; color:var(--muted); }
        .dest-card .count { font-weight:600; color:var(--ink); }
    </style>
@endpush

@section('content')
<div class="site-shell">
    <section class="site-hero">
        <p class="eyebrow">Destinations</p>
        <h1>Where you can stay</h1>
        <p class="lede">
            Every destination here is built from listings that are live right now — if a city
            appears, there is something in it to book.
        </p>
    </section>

    <section class="site-section">
        <form method="GET" action="{{ route('destinations.index') }}" class="dest-search">
            <input type="search" name="q" value="{{ $search }}"
                   placeholder="Search a city or country" aria-label="Search destinations">
            <button type="submit" class="site-cta">Search</button>
        </form>

        @if ($destinations->isEmpty())
            <div class="site-empty">
                @if ($search !== '')
                    No destinations match “{{ $search }}”.
                    <a href="{{ route('destinations.index') }}">See all destinations</a>.
                @else
                    No published listings yet. Once properties go live their destinations appear here
                    automatically.
                @endif
            </div>
        @else
            <div class="site-grid cols-3">
                @foreach ($destinations as $destination)
                    @php
                        $cover = ($covers[$destination->city] ?? collect())
                            ->first(fn ($p) => $p->photos->isNotEmpty());
                        $photoUrl = $cover?->photos->first()?->url;
                    @endphp
                    <a class="dest-card"
                       href="{{ route('properties.index', ['destination' => Str::slug($destination->city)]) }}"
                       data-track-audience="traveler" data-track-cta="destination_card">
                        <div class="dest-card-img"
                             @if ($photoUrl) style="background-image:url('{{ $photoUrl }}');" @endif></div>
                        <div class="dest-card-body">
                            <h3>{{ $destination->city }}</h3>
                            <div class="meta">
                                {{ $destination->country }} ·
                                <span class="count">{{ $destination->listing_count }}</span>
                                {{ Str::plural('stay', $destination->listing_count) }}
                                @if ($destination->from_cents)
                                    · from ${{ number_format($destination->from_cents / 100) }}
                                @endif
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    <section class="site-section">
        <div class="site-card">
            <h3>Own a property in one of these places?</h3>
            <p style="margin:0 0 16px;">
                List it alongside them. Members advertise vacation properties
                inventory through the Managed Listing Program.
            </p>
            <a href="{{ route('hosts.show') }}" class="site-cta"
               data-track-audience="host" data-track-cta="destinations_become_host">Become a host</a>
        </div>
    </section>
</div>
@endsection
