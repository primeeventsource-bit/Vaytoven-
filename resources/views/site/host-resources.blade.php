@extends('layouts.site')

@section('title', 'Host resources')
@section('meta_description', 'Guides for Vaytoven hosts and members: building a listing, photos, pricing, responding to inquiries and offers, and account security.')

@section('content')
<div class="site-shell">
    <section class="site-hero">
        <p class="eyebrow">Host resources</p>
        <h1>Everything you need to run a listing</h1>
        <p class="lede">
            Written for members and hosts. These are the same articles the Help Center serves, so
            they stay in step — anything updated there shows up here.
        </p>
    </section>

    <section class="site-section">
        @if ($grouped->isEmpty())
            <div class="site-empty">
                No host guides have been published yet.
                <a href="{{ route('help.index') }}">Browse the full Help Center</a>.
            </div>
        @else
            @foreach ($grouped as $category => $articles)
                <div style="margin-bottom:34px;">
                    <h2>{{ ucwords(str_replace('-', ' ', $category)) }}</h2>
                    <div class="site-grid cols-2" style="margin-top:14px;">
                        @foreach ($articles as $article)
                            <article class="site-card">
                                <h3 style="margin:0 0 6px;">
                                    <a href="{{ route('help.show', $article->slug) }}">{{ $article->title }}</a>
                                </h3>
                                <p style="margin:0;">{{ $article->summary }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endif
    </section>

    <section class="site-section">
        <div class="site-grid cols-2">
            <div class="site-card">
                <h3>Photo guidance</h3>
                <p style="margin:0 0 8px;">
                    Landscape images at least 1600px wide reproduce well on every surface, including
                    the destination cards. The first photo is the one travelers see in search, so
                    lead with the room that sells the place.
                </p>
                <p style="margin:0;">
                    Shoot in daylight, keep the frame level, and show the space rather than the
                    detail — close-ups belong further down the set.
                </p>
            </div>
            <div class="site-card">
                <h3>Offers expire in 24 hours</h3>
                <p style="margin:0 0 8px;">
                    An offer on your listing lapses exactly 24 hours after it was submitted. If you
                    take no action it moves to EXPIRED automatically and the buyer is free to look
                    elsewhere.
                </p>
                <p style="margin:0;">
                    Expired offers stay on your dashboard with the amount, time and IP intact, so
                    you keep the record.
                </p>
            </div>
        </div>

        <div style="margin-top:24px; display:flex; gap:14px; flex-wrap:wrap;">
            <a href="{{ route('offers.index') }}" class="site-cta"
               data-track-audience="host" data-track-cta="host_resources_offers">View my inquiries &amp; offers</a>
            <a href="{{ route('earnings-calculator') }}" class="site-cta"
               style="background:none; color:var(--magenta); border:1px solid var(--line);"
               data-track-audience="host" data-track-cta="host_resources_calculator">Estimate earnings</a>
        </div>
    </section>
</div>
@endsection
