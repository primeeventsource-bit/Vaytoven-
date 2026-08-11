@extends('layouts.site')

@section('title', 'Careers')
@section('meta_description', 'Open roles at Vaytoven Technologies LLC — building the platform behind vacation property listings, memberships and the Vacation Club.')

@section('content')
<div class="site-shell">
    <section class="site-hero">
        <p class="eyebrow">Careers</p>
        <h1>Build the platform behind the stay</h1>
        <p class="lede">
            Vaytoven Technologies LLC is a small team building listing, membership and marketing
            software for vacation property owners. We hire people who like owning a problem
            end to end.
        </p>
    </section>

    <section class="site-section">
        <h2>Open positions</h2>

        @if ($openings->isEmpty())
            {{-- Deliberately honest. Inventing roles to fill the page would
                 waste real applicants' time. --}}
            <div class="site-empty" style="margin-top:18px;">
                There are currently no open positions. Please check back for future opportunities.
            </div>
        @else
            <div class="site-grid" style="margin-top:18px;">
                @foreach ($openings as $opening)
                    <article class="site-card">
                        <div style="display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; align-items:baseline;">
                            <h3 style="margin:0;">
                                <a href="{{ route('careers.show', $opening) }}">{{ $opening->title }}</a>
                            </h3>
                            <span style="font-size:12.5px; color:var(--muted);">
                                {{ $opening->department }} · {{ $opening->location }} · {{ $opening->employmentTypeLabel() }}
                            </span>
                        </div>
                        <p style="margin:10px 0 0;">{{ $opening->summary }}</p>
                        <p style="margin:12px 0 0;">
                            <a href="{{ route('careers.show', $opening) }}">View role and apply</a>
                        </p>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <section class="site-section">
        <h2>How we work</h2>
        <div class="site-grid cols-3" style="margin-top:16px;">
            <div class="site-card">
                <h3>Small surface, real ownership</h3>
                <p style="margin:0;">
                    You will not be the seventh person on a feature. Whoever builds something also
                    decides how it should behave and looks after it once it is live.
                </p>
            </div>
            <div class="site-card">
                <h3>Remote-first</h3>
                <p style="margin:0;">
                    The company is headquartered in West Palm Beach, Florida. Roles state their own
                    location requirement — where one is remote, it is genuinely remote.
                </p>
            </div>
            <div class="site-card">
                <h3>We read every application</h3>
                <p style="margin:0;">
                    Applications go to a person, not a keyword filter. If a role is closed we take
                    it down rather than leaving it up to collect résumés.
                </p>
            </div>
        </div>
    </section>
</div>
@endsection
