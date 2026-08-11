@extends('layouts.site')

@section('title', $release->title)
@section('meta_description', $release->excerpt)

@section('content')
<div class="site-shell">
    <section class="site-hero">
        <p class="eyebrow"><a href="{{ route('press.index') }}">Press</a></p>
        <h1>{{ $release->title }}</h1>
        <time style="font-size:13.5px; color:var(--muted);">{{ $release->published_at->format('j F Y') }}</time>
    </section>

    <section class="site-section" style="max-width:70ch;">
        <p style="font-size:17px; color:var(--ink);">{{ $release->excerpt }}</p>
        <div style="color:var(--muted); line-height:1.75; margin-top:18px;">{!! nl2br(e($release->body)) !!}</div>

        <div class="site-card" style="margin-top:32px;">
            <h3>Media contact</h3>
            <p style="margin:0;"><a href="mailto:{{ $mediaEmail }}">{{ $mediaEmail }}</a></p>
        </div>

        <p class="site-note">
            About Vaytoven Technologies LLC — a technology and marketing platform for vacation
            property owners and members. It is not a real estate brokerage, travel agency or
            property manager, and does not own the properties listed by its members.
        </p>
    </section>
</div>
@endsection
