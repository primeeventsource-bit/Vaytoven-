<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Event Centers · Conventions &amp; Expos · Vaytoven</title>
    <meta name="description" content="Upcoming conventions, expos, trade shows and conferences at five of America's largest convention destinations — then explore Vaytoven property advertisements in each city.">
    @include('partials.favicon')
    @include('partials.google-tag')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Serif+4:opsz,wght@8..60,400;8..60,500;8..60,600;8..60,700&family=Geist:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @include('partials.brand-styles')
    @include('partials.footer-styles')
    <style>
        /* Source Serif 4 is a variable font with an optical-size axis.
           Pinned so the browser holds one cut at every size rather than
           selecting a more display-like one as type grows. The property
           inherits, so this single declaration reaches every heading below it.

           Fraunces was here until its letterforms — the curled g, the flared
           C and V — kept reading as wavy at heading sizes. No axis removed
           that: WONK and SOFT were already at 0 and rendering with them
           pinned was pixel-identical, so the face itself had to change. */
        html { font-optical-sizing: none; }

        .ec-hero {
            background: linear-gradient(135deg, #fdf2f8 0%, #f5f3ff 100%);
            padding: 64px 24px clamp(44px, 5vw, 72px);
            text-align: center;
        }
        .ec-hero .eyebrow {
            font-size: 12px; letter-spacing: .12em; text-transform: uppercase;
            color: var(--magenta); font-weight: 600;
        }
        .ec-hero h1 {
            font-family: 'Source Serif 4', serif; font-size: clamp(32px, 4.6vw, 50px);
            font-weight: 600; letter-spacing: -.02em; line-height: 1.12;
            margin: 14px auto 18px; max-width: 780px;
        }
        .ec-hero h1 em {
            font-style: normal;
            background: var(--gradient); -webkit-background-clip: text; background-clip: text; color: transparent;
        }
        .ec-hero p {
            font-size: 17px; color: var(--ink); line-height: 1.55;
            max-width: 660px; margin: 0 auto;
        }

        .ec-shell { max-width: 1100px; margin: 0 auto; padding: 56px 24px 72px; }

        .ec-grid { display: grid; gap: 22px; grid-template-columns: 1fr; }
        @media (min-width: 760px) { .ec-grid { grid-template-columns: 1fr 1fr; } }

        .ec-card {
            background: #fff; border: 1px solid var(--line); border-radius: 18px;
            display: flex; flex-direction: column; overflow: hidden;
            box-shadow: 0 12px 32px -20px rgba(123,44,191,.20);
        }
        .ec-card-body { padding: 22px 26px; display: flex; flex-direction: column; flex: 1; }

        /* The photo is a second route to the same calendar the button opens.
           The zoom happens on the image inside a fixed 16:9 frame, so the card
           never changes size and the grid never reflows under the pointer. */
        .ec-card-photo {
            display: block; aspect-ratio: 16 / 9; overflow: hidden;
            background: #f3eef8; border-bottom: 1px solid var(--line);
        }
        .ec-card-photo img {
            display: block; width: 100%; height: 100%; object-fit: cover;
            transition: transform .5s ease;
        }
        .ec-card-photo:hover img, .ec-card-photo:focus-visible img { transform: scale(1.045); }
        @media (prefers-reduced-motion: reduce) {
            .ec-card-photo img { transition: none; }
            .ec-card-photo:hover img, .ec-card-photo:focus-visible img { transform: none; }
        }
        .ec-card-place {
            font-size: 11.5px; letter-spacing: .12em; text-transform: uppercase;
            color: var(--muted); font-weight: 600;
        }
        .ec-card h2 {
            font-family: 'Source Serif 4', serif; font-size: 25px; font-weight: 600;
            letter-spacing: -.01em; margin: 9px 0 10px;
        }
        .ec-card p { font-size: 14.5px; color: var(--ink); line-height: 1.55; margin: 0 0 16px; }

        .ec-card-count {
            font-size: 13px; color: var(--muted); margin: 0 0 18px;
            padding-top: 14px; border-top: 1px solid var(--line);
        }
        .ec-card-count strong { color: var(--purple); font-weight: 600; }

        .ec-card-actions { margin-top: auto; display: flex; gap: 10px; flex-wrap: wrap; }
        .ec-btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 11px 20px; border-radius: 999px; font-size: 14px; font-weight: 600;
            text-decoration: none; transition: transform .12s; white-space: nowrap;
        }
        .ec-btn:hover { transform: translateY(-1px); text-decoration: none; }
        .ec-btn-primary { background: var(--gradient); color: #fff; box-shadow: 0 8px 24px -10px rgba(214,51,132,.42); }
        .ec-btn-secondary { border: 1px solid var(--line); color: var(--ink); background: #fff; }
        .ec-btn-secondary:hover { border-color: var(--purple); color: var(--purple); }

        /* A licence term, not a footnote: CC BY and CC BY-SA are conditional on
           the author being named where the photograph is shown. */
        .ec-credits {
            margin: 34px 0 0; font-size: 12px; line-height: 1.7; color: var(--muted);
        }
        .ec-credits a { color: inherit; text-decoration: underline; }

    </style>
</head>
<body>
    @include('partials.top-nav', ['current' => 'event-centers'])

    <header class="ec-hero">
        <div class="eyebrow">Event Centers</div>
        <h1>Going somewhere big? <em>Find your place</em> with Vaytoven.</h1>
        <p>
            Explore upcoming conventions, expos, trade shows, conferences and major events
            happening at five of the country's largest convention destinations. Check what's
            coming up, then explore Vaytoven property listings for your destination.
        </p>
    </header>

    <main class="ec-shell">
        <div class="ec-grid">
            @foreach ($centers as $center)
                {{-- The two cards that open above the fold on a desktop screen
                     load their photograph straight away; the rest wait until
                     they are scrolled towards. --}}
                @php($eager = $loop->index < 2)

                <article class="ec-card">
                    {{-- The whole photograph opens the venue's calendar, which
                         is what the button beneath it does too. Hidden from
                         keyboard and screen readers precisely because it is a
                         duplicate: it would otherwise be a second tab stop and
                         a second announcement of the same destination. --}}
                    <a class="ec-card-photo"
                       href="{{ $center['calendar_url'] }}"
                       target="_blank" rel="noopener noreferrer"
                       tabindex="-1" aria-hidden="true"
                       data-vyt-event="advertisement.clicked"
                       data-vyt-subject-type="event_center"
                       data-vyt-subject="{{ $center['slug'] }}">
                        <img src="{{ asset('images/event-centers/'.$center['photo']['file'].'-1200.jpg') }}"
                             srcset="{{ asset('images/event-centers/'.$center['photo']['file'].'-800.jpg') }} 800w,
                                     {{ asset('images/event-centers/'.$center['photo']['file'].'-1200.jpg') }} 1200w"
                             sizes="(min-width: 760px) 50vw, 100vw"
                             width="1200" height="675"
                             alt="{{ $center['photo']['alt'] }}"
                             loading="{{ $eager ? 'eager' : 'lazy' }}"
                             decoding="async"
                             @if ($eager) fetchpriority="high" @endif>
                    </a>

                    <div class="ec-card-body">
                        <div class="ec-card-place">{{ $center['city'] }}, {{ $center['region'] }}</div>

                        <h2>{{ $center['name'] }}</h2>

                        <p>{{ $center['blurb'] }}</p>

                        {{-- The live count, whatever it is.
                             A page that promises somewhere to stay near McCormick
                             Place while Vaytoven advertises nothing in Chicago
                             wastes the click. Saying so costs nothing and the
                             number climbs on its own as listings arrive. --}}
                        <div class="ec-card-count">
                            @if ($center['listings'] > 0)
                                <strong>{{ $center['listings'].' '.Str::plural('advertisement', $center['listings']).' in '.$center['city'] }}</strong>
                            @else
                                {{ 'No '.$center['city'].' advertisements yet' }} — new listings are added regularly.
                            @endif
                        </div>

                        <div class="ec-card-actions">
                            {{-- The venue publishes its own schedule and is the
                                 authority on it. Vaytoven links out rather than
                                 copying dates that go stale the moment one moves.
                                 noopener because these open in a new tab. --}}
                            <a class="ec-btn ec-btn-secondary"
                               href="{{ $center['calendar_url'] }}"
                               target="_blank" rel="noopener noreferrer"
                               data-vyt-event="advertisement.clicked"
                               data-vyt-subject-type="event_center"
                               data-vyt-subject="{{ $center['slug'] }}">
                                View event calendar
                                <span aria-hidden="true">↗</span>
                            </a>

                            <a class="ec-btn ec-btn-primary"
                               href="{{ route('properties.index', ['event_center' => $center['slug']]) }}">
                                Explore properties nearby
                            </a>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        <p class="ec-credits">
            Venue photographs:
            @foreach ($centers as $center)
                @php($photo = $center['photo'])
                <a href="{{ $photo['source'] }}" target="_blank" rel="noopener noreferrer">{{ $center['name'] }}</a>
                by {{ $photo['by'] }}@if ($photo['license_url']),
                    <a href="{{ $photo['license_url'] }}" target="_blank" rel="noopener noreferrer">{{ $photo['license'] }}</a>@else,
                    {{ $photo['license'] }}@endif{{ $loop->last ? '.' : ';' }}
            @endforeach
            Via Wikimedia Commons.
        </p>

    </main>

    @include('partials.site-footer')

    <script src="/vyt-track.js" defer></script>
</body>
</html>
