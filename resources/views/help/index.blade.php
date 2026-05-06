@extends('help.layout')

@section('title', 'Help center')

@section('content')
    <div class="help-eyebrow">Vaytoven help center</div>
    <h1 class="help-title">How can we help?</h1>

    <div class="help-search">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.35-4.35"/></svg>
        <input type="search" id="help-search-input" placeholder="Search articles — cancellation, payouts, hosting…" autocomplete="off" data-track-audience="{{ $audience?->value ?? 'all' }}" data-track-cta="help_search">
        <div class="help-results" id="help-search-results" style="display:none;"></div>
    </div>

    <div class="help-audiences">
        <a class="help-audience-chip {{ $audience === null ? 'is-active' : '' }}" href="{{ route('help.index') }}">All</a>
        <a class="help-audience-chip {{ $audience?->value === 'traveler' ? 'is-active' : '' }}" href="{{ route('help.index', ['audience' => 'traveler']) }}" data-track-audience="traveler" data-track-cta="help_filter_traveler">Travelers</a>
        <a class="help-audience-chip {{ $audience?->value === 'host' ? 'is-active' : '' }}" href="{{ route('help.index', ['audience' => 'host']) }}" data-track-audience="host" data-track-cta="help_filter_host">Hosts</a>
        <a class="help-audience-chip {{ $audience?->value === 'member' ? 'is-active' : '' }}" href="{{ route('help.index', ['audience' => 'member']) }}" data-track-audience="member" data-track-cta="help_filter_member">Members</a>
    </div>

    @forelse ($categories as $category => $articles)
        <section class="help-category">
            <h2>{{ ucfirst(str_replace('-', ' ', $category)) }}</h2>
            <ul>
                @foreach ($articles as $article)
                    <li>
                        <a href="{{ route('help.show', $article->slug) }}" data-track-audience="{{ $article->audience->value }}" data-track-cta="help_article_open" data-track-meta-slug="{{ $article->slug }}">
                            <strong>{{ $article->title }}</strong>
                            <span class="summary">{{ $article->summary }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <p>No articles yet. Try a different audience filter.</p>
    @endforelse

    <script>
        (function () {
            var input = document.getElementById('help-search-input');
            var results = document.getElementById('help-search-results');
            var audience = '{{ $audience?->value ?? '' }}';
            var t;

            function clear() {
                results.innerHTML = '';
                results.style.display = 'none';
            }

            function render(items) {
                if (!items.length) {
                    results.innerHTML = '<div class="help-result"><em>No matches — try simpler words like "refund" or "cancel".</em></div>';
                    results.style.display = '';
                    return;
                }
                results.innerHTML = items.map(function (a) {
                    return '<a class="help-result" href="' + a.url + '" data-track-audience="' + a.audience + '" data-track-cta="help_search_result_click" data-track-meta-slug="' + a.slug + '">'
                        + '<div class="help-result-title">' + escapeHtml(a.title) + '</div>'
                        + '<div class="help-result-summary">' + escapeHtml(a.summary) + '</div>'
                        + '</a>';
                }).join('');
                results.style.display = '';
            }

            function escapeHtml(s) {
                return String(s).replace(/[&<>"']/g, function (c) {
                    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
                });
            }

            input.addEventListener('input', function () {
                var q = input.value.trim();
                clearTimeout(t);
                if (q.length < 2) { clear(); return; }
                t = setTimeout(function () {
                    var url = '/help/search?q=' + encodeURIComponent(q) + (audience ? '&audience=' + audience : '');
                    fetch(url, { headers: { 'Accept': 'application/json' } })
                        .then(function (r) { return r.json(); })
                        .then(function (data) { render(data.results || []); })
                        .catch(clear);
                }, 180);
            });

            document.addEventListener('click', function (e) {
                if (!input.contains(e.target) && !results.contains(e.target)) clear();
            });
        })();
    </script>
@endsection
