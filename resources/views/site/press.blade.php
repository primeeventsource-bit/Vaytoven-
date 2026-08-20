@extends('layouts.site')

@section('title', 'Press & media')
@section('meta_description', 'Company announcements, brand assets and media contact for Vaytoven Technologies LLC.')

@section('content')
<div class="site-shell">
    <section class="site-hero">
        <p class="eyebrow">Press &amp; media</p>
        <h1>Announcements and media resources</h1>
        <p class="lede">
            Company news from Vaytoven Technologies LLC, plus brand assets and a direct line for
            journalists.
        </p>
    </section>

    <section class="site-section">
        <h2>Announcements</h2>

        @if ($releases->isEmpty())
            {{-- No fabricated coverage, awards or partnerships. The page ships
                 empty until there is something real to announce. --}}
            <div class="site-empty" style="margin-top:18px;">
                No announcements have been published yet. Media inquiries are always welcome —
                see the contact below.
            </div>
        @else
            <div class="site-grid" style="margin-top:18px;">
                @foreach ($releases as $release)
                    <article class="site-card">
                        <time style="font-size:12.5px; color:var(--muted); letter-spacing:.04em;">
                            {{ et($release->published_at, 'j F Y') }}
                        </time>
                        <h3 style="margin:8px 0 6px;">
                            <a href="{{ route('press.show', $release) }}">{{ $release->title }}</a>
                        </h3>
                        <p style="margin:0;">{{ $release->excerpt }}</p>
                    </article>
                @endforeach
            </div>

            @if ($releases->hasPages())
                <div style="margin-top:24px;">{{ $releases->links() }}</div>
            @endif
        @endif
    </section>

    <section class="site-section">
        <div class="site-grid cols-2">
            <div class="site-card">
                <h3>Media contact</h3>
                <p style="margin:0 0 10px;">
                    For interviews, comment or factual checks, write to us and say what you're
                    working on and your deadline.
                </p>
                <p style="margin:0;"><a href="mailto:{{ $mediaEmail }}">{{ $mediaEmail }}</a></p>
            </div>

            <div class="site-card">
                <h3>Brand assets</h3>
                <p style="margin:0 0 14px;">
                    The Vaytoven mark is the V-pin in the site header. Please use it on a light
                    background with clear space around it, and don't recolor or stretch it.
                </p>
                <div style="display:flex; align-items:center; gap:14px; padding:16px; background:var(--bg); border-radius:10px;">
                    <svg viewBox="0 0 64 64" width="44" height="44" aria-label="Vaytoven logo">
                        <defs>
                            <linearGradient id="vyt-press-grad" x1="0" y1="0" x2="1" y2="1">
                                <stop offset="0%" stop-color="#FF3D8A"/>
                                <stop offset="50%" stop-color="#D63384"/>
                                <stop offset="100%" stop-color="#7B2CBF"/>
                            </linearGradient>
                        </defs>
                        <path fill="url(#vyt-press-grad)" d="M10 8h12l10 30 10-30h12L34 56h-8z"/>
                        <circle cx="48" cy="14" r="8" fill="url(#vyt-press-grad)"/>
                        <circle cx="48" cy="14" r="3" fill="#fff"/>
                    </svg>
                    <div style="font-size:13px; color:var(--muted);">
                        Gradient <code style="font-family:'SFMono-Regular',Consolas,monospace;">#FF3D8A → #D63384 → #7B2CBF</code><br>
                        Wordmark: Fraunces · Body: Geist
                    </div>
                </div>
            </div>
        </div>

        <p class="site-note">
            Vaytoven Technologies LLC is a technology and marketing platform. It is not a real
            estate brokerage, travel agency or property manager, and does not own the properties
            its members list. Please describe it accordingly.
        </p>
    </section>
</div>
@endsection
