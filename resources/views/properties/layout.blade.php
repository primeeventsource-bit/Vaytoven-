<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Stays') · Vaytoven</title>
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Geist:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Fraunces is a variable font with an optical-size axis. On auto the
           browser picks a more decorative cut as type gets larger — flared
           serifs and exaggerated curves — which is what reads as wavy on
           headings. Pinned here; the property inherits, so this one
           declaration reaches every heading below it. */
        html { font-optical-sizing: none; }

        :root {
            --pink:#FF3D8A; --magenta:#D63384; --purple:#7B2CBF;
            --gradient: linear-gradient(135deg,#FF3D8A 0%,#D63384 50%,#7B2CBF 100%);
            --ink:#1d1f21; --muted:#6b7280; --line:#e7e5e4; --bg:#fafaf9;
        }
        * { box-sizing: border-box; }
        body { margin:0; font-family:'Geist',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; color:var(--ink); background:var(--bg); line-height:1.55; -webkit-font-smoothing:antialiased; }
        a { color:var(--purple); text-decoration:none; }
        a:hover { text-decoration:underline; }

        .vyt-nav { background:#fff; border-bottom:1px solid var(--line); padding:14px 24px; display:flex; align-items:center; gap:18px; position:sticky; top:0; z-index:30; }
        .vyt-nav .brand { display:flex; align-items:center; gap:10px; color:var(--ink); font-weight:600; font-size:16px; }
        .vyt-nav .brand svg { width:30px; height:30px; }
        .vyt-nav-links { display:flex; gap:18px; margin-left:24px; }
        .vyt-nav-links a { color:var(--ink); font-size:14px; font-weight:500; }
        .vyt-nav-links a:hover { color:var(--purple); text-decoration:none; }
        .vyt-nav-spacer { flex:1; }

        .props-shell { max-width:1200px; margin:0 auto; padding: 32px 24px 80px; }
        .props-eyebrow { font-size:12px; letter-spacing:.12em; text-transform:uppercase; color:var(--muted); font-weight:600; }
        .props-title { font-family:'Fraunces',serif; font-size:clamp(28px,3.5vw,42px); font-weight:600; letter-spacing:-.02em; margin:8px 0 18px; }
        .props-meta { font-size:13px; color:var(--muted); }

        /* Filter bar */
        .props-filters {
            background:#fff; border:1px solid var(--line); border-radius:14px;
            padding:14px 18px; margin-bottom:28px;
            display:grid; grid-template-columns: 1fr; gap:10px;
        }
        @media (min-width:760px) { .props-filters { grid-template-columns: 2fr 1fr 1fr 1fr auto; } }
        .props-filter { display:flex; flex-direction:column; gap:4px; }
        .props-filter label { font-size:11px; letter-spacing:.08em; text-transform:uppercase; color:var(--muted); font-weight:600; }
        .props-filter input { padding:9px 12px; border:1px solid var(--line); border-radius:8px; font-size:14px; outline:none; background:var(--bg); }
        .props-filter input:focus { border-color: var(--magenta); background:#fff; }
        .props-filter-submit { background:var(--gradient); color:#fff; border:0; padding:0 22px; border-radius:8px; font-weight:600; font-size:14px; cursor:pointer; }
        .props-filter-submit:hover { transform: translateY(-1px); }

        /* Active filter chips */
        .props-active-filters { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:18px; }
        .props-chip { padding:5px 12px; background:#f5f3ff; color:var(--purple); border-radius:999px; font-size:12px; font-weight:500; }
        .props-chip a { margin-left:6px; color:var(--purple); }

        /* Grid */
        .props-grid { display:grid; gap:18px; grid-template-columns: 1fr; }
        @media (min-width:600px) { .props-grid { grid-template-columns: 1fr 1fr; } }
        @media (min-width:980px) { .props-grid { grid-template-columns: 1fr 1fr 1fr; } }
        .props-card {
            background:#fff; border:1px solid var(--line); border-radius:14px;
            overflow:hidden; transition: transform .12s, box-shadow .15s;
            display:flex; flex-direction:column; color:var(--ink); text-decoration:none;
        }
        .props-card:hover { transform:translateY(-3px); box-shadow:0 12px 32px -12px rgba(123,44,191,.18); text-decoration:none; }
        .props-card-img { aspect-ratio: 4/3; width:100%; object-fit:cover; background:#f5f3ff; }
        .props-card-body { padding:14px 16px; flex:1; display:flex; flex-direction:column; gap:6px; }
        .props-card h3 { font-family:'Fraunces',serif; font-size:17px; font-weight:600; margin:0; letter-spacing:-.005em; }
        .props-card-loc { font-size:13px; color:var(--muted); }
        .props-card-meta { font-size:12px; color:var(--muted); margin-top:auto; }
        .props-card-price { font-weight:600; font-size:14px; color:var(--ink); }
        .props-card-price .num { font-family:'Fraunces',serif; font-size:18px; font-weight:600; }

        /* Empty state */
        .props-empty {
            background:#fff; border:1px solid var(--line); border-radius:14px;
            padding:60px 24px; text-align:center; color:var(--muted);
        }
        .props-empty h3 { font-family:'Fraunces',serif; font-size:22px; color:var(--ink); margin:0 0 8px; font-weight:600; }

        /* Pagination */
        /* Laravel's default paginator markup carries Tailwind utility classes
           that this theme does not load, so its inner inline-flex kept its
           natural width and pushed /properties 25px sideways at 390px. The
           control is allowed to wrap and is stopped from exceeding the column;
           the deeper fix is a published paginator view, which is a bigger
           change than the bug warrants. */
        .props-pagination { margin-top:32px; display:flex; justify-content:center; gap:6px; flex-wrap:wrap; max-width:100%; }
        .props-pagination * { max-width:100%; }
        .props-pagination nav, .props-pagination [class*="inline-flex"] {
            display:flex; flex-wrap:wrap; justify-content:center; gap:6px; max-width:100%;
        }
        .props-pagination a, .props-pagination span {
            padding:8px 14px; border-radius:8px; font-size:13px; font-weight:500;
            background:#fff; border:1px solid var(--line); color:var(--ink);
        }
        .props-pagination .active span, .props-pagination .active { background: var(--gradient); color:#fff; border-color: transparent; }

        /* Detail view */
        .props-detail-back { font-size:13px; color:var(--muted); }

        /* Sits above the name, quieter than it — the name is who you are
           dealing with, the number is how you refer to them. */
        .props-member-id {
            font-size:12px; letter-spacing:.06em; text-transform:uppercase;
            color:var(--muted); font-weight:600; margin:0 0 2px;
        }

        /* Save button. Sits beside the title, so it stays visible without
           following the visitor down the page. */
        .props-save-btn {
            display:inline-flex; align-items:center; gap:7px;
            padding:9px 18px; border:1px solid var(--line); border-radius:999px;
            background:#fff; font-size:14px; font-weight:600; cursor:pointer;
            white-space:nowrap; color:inherit; text-decoration:none;
        }
        .props-save-btn:hover { border-color:var(--purple); color:var(--purple); }
        .props-save-btn.is-saved { border-color:var(--purple); color:var(--purple); background:rgba(123,44,191,.06); }
        .props-detail-hero {
            display:grid; gap:8px; grid-template-columns: 1fr; margin-bottom:28px;
            border-radius:14px; overflow:hidden;
        }
        @media (min-width:760px) { .props-detail-hero { grid-template-columns: 2fr 1fr; grid-template-rows: 1fr 1fr; } }
        .props-detail-hero img { width:100%; height:100%; object-fit:cover; aspect-ratio: 4/3; background:#f5f3ff; }
        .props-detail-hero img:first-child { grid-row: 1/3; aspect-ratio: 4/3; }
        @media (max-width:759px) { .props-detail-hero img:not(:first-child) { display:none; } }

        .props-detail-grid { display:grid; gap:32px; grid-template-columns: 1fr; }
        @media (min-width:900px) { .props-detail-grid { grid-template-columns: 2fr 1fr; } }

        .props-detail h1 { font-family:'Fraunces',serif; font-size:clamp(28px,3vw,38px); font-weight:600; letter-spacing:-.02em; margin: 12px 0 6px; }
        .props-detail-loc { font-size:14px; color:var(--muted); margin-bottom:18px; }
        .props-detail-stats { display:flex; gap:24px; padding:18px 0; border-top:1px solid var(--line); border-bottom:1px solid var(--line); margin-bottom:24px; flex-wrap:wrap; }
        .props-detail-stat strong { font-family:'Fraunces',serif; font-size:18px; font-weight:600; display:block; }
        .props-detail-stat span { font-size:12px; color:var(--muted); }
        .props-detail-section { margin: 28px 0; }
        .props-detail-section h2 { font-family:'Fraunces',serif; font-size:20px; font-weight:600; margin:0 0 12px; }
        .props-detail-section p { font-size:15px; line-height:1.7; }

        .props-amenity-list { display:grid; grid-template-columns: 1fr 1fr; gap:8px 18px; padding:0; margin:0; list-style:none; }
        @media (min-width:600px) { .props-amenity-list { grid-template-columns: 1fr 1fr 1fr; } }
        .props-amenity-list li { font-size:14px; padding:6px 0; }
        .props-amenity-list li::before { content:"✓ "; color:var(--magenta); font-weight:700; }

        .props-book-card {
            background:#fff; border:1px solid var(--line); border-radius:14px;
            padding:22px; position:sticky; top:80px;
        }
        .props-book-price { font-family:'Fraunces',serif; font-size:28px; font-weight:600; }
        .props-book-price small { font-size:14px; color:var(--muted); font-weight:400; }
        .props-book-cta {
            display:block; text-align:center; margin-top:18px; padding:13px 22px;
            background:var(--gradient); color:#fff; border-radius:999px; border:0;
            font-weight:600; font-size:15px; text-decoration:none; cursor:pointer;
        }
        .props-book-cta:hover { transform:translateY(-1px); text-decoration:none; }
        .props-book-fineprint { font-size:12px; color:var(--muted); margin-top:14px; }

        /* ── Photo carousel (cards + detail hero) ──────────────────── */
        .vyt-carousel {
            position: relative; width: 100%; aspect-ratio: 4/3;
            background: #f5f3ff; border-radius: inherit; overflow: hidden;
        }
        .vyt-carousel.is-hero {
            aspect-ratio: 16/9; max-height: 480px; border-radius: 14px;
            margin-bottom: 28px;
        }
        .vyt-carousel-track {
            display: flex; width: 100%; height: 100%;
            overflow-x: auto; scroll-snap-type: x mandatory;
            scrollbar-width: none; -ms-overflow-style: none;
            scroll-behavior: smooth;
        }
        .vyt-carousel-track::-webkit-scrollbar { display: none; }
        .vyt-carousel-slide {
            flex: 0 0 100%; height: 100%;
            scroll-snap-align: start; scroll-snap-stop: always;
            position: relative;
        }
        .vyt-carousel-slide img {
            width: 100%; height: 100%; object-fit: cover; display: block;
            user-select: none; -webkit-user-drag: none;
        }
        .vyt-carousel-arrow {
            position: absolute; top: 50%; transform: translateY(-50%);
            background: rgba(255,255,255,.92); border: 0; cursor: pointer;
            width: 36px; height: 36px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: var(--ink); font-size: 18px; line-height: 1;
            box-shadow: 0 4px 12px rgba(0,0,0,.12);
            transition: opacity .15s, transform .15s, background .15s;
            opacity: 0; z-index: 2;
            font-family: inherit;
        }
        .vyt-carousel:hover .vyt-carousel-arrow,
        .vyt-carousel-arrow:focus { opacity: 1; }
        .vyt-carousel-arrow:hover { background: #fff; transform: translateY(-50%) scale(1.06); }
        .vyt-carousel-arrow.is-prev { left: 10px; }
        .vyt-carousel-arrow.is-next { right: 10px; }
        @media (hover: none) { .vyt-carousel-arrow { display: none; } }
        .vyt-carousel-dots {
            position: absolute; bottom: 12px; left: 50%; transform: translateX(-50%);
            display: flex; gap: 6px; z-index: 2;
        }
        .vyt-carousel-dot {
            width: 6px; height: 6px; border-radius: 50%;
            border: 0; cursor: pointer; padding: 0;
            background: rgba(255,255,255,.55);
            transition: transform .15s, background .15s, width .15s;
        }
        .vyt-carousel-dot:hover { background: rgba(255,255,255,.85); }
        .vyt-carousel-dot.is-active { background: #fff; width: 18px; border-radius: 999px; }
        .props-card .vyt-carousel { border-radius: 0; }

        /* ── Filter rail (collapsible details element) ─────────────── */
        .props-filter-rail {
            background:#fff; border:1px solid var(--line); border-radius:14px;
            padding: 0 18px; margin-bottom: 22px;
        }
        .props-filter-rail summary {
            list-style: none; cursor: pointer;
            padding: 14px 0; display:flex; justify-content:space-between; align-items:center;
            font-family:'Geist',sans-serif; font-weight: 600; font-size: 14px; outline: 0;
        }
        .props-filter-rail summary::-webkit-details-marker { display: none; }
        .props-filter-chevron { color: var(--muted); transition: transform .15s; font-size: 14px; }
        .props-filter-rail[open] .props-filter-chevron { transform: rotate(180deg); }
        .props-filter-form {
            padding: 4px 0 18px; border-top: 1px solid var(--line);
            display: grid; gap: 22px;
        }
        @media (min-width: 720px) { .props-filter-form { grid-template-columns: 240px 1fr auto; align-items: end; } }
        .props-filter-section h4 {
            font-size: 11px; letter-spacing:.08em; text-transform: uppercase;
            color: var(--muted); font-weight: 600; margin: 0 0 10px;
        }
        /* Grid and flex children default to min-width:auto, so a track is never
           allowed to be narrower than its content's minimum. A number input's
           minimum is its default character width — about 218px here — and two
           of them plus the gap forced this rail to 448px inside a 390px screen,
           pushing the whole page sideways. min-width:0 lets them shrink. */
        .props-filter-form > * { min-width: 0; }
        .props-filter-price { display: flex; gap: 12px; min-width: 0; }
        .props-filter-price label { flex: 1 1 0; min-width: 0; display: flex; flex-direction: column; gap: 4px; font-size: 12px; color: var(--muted); }
        .props-filter-price input {
            padding: 9px 12px; border: 1px solid var(--line); border-radius: 8px;
            font-size: 14px; outline: none; background: var(--bg); font-family: 'Geist', sans-serif;
            transition: border-color .12s, background .12s;
            width: 100%; min-width: 0;
        }
        .props-filter-price input:focus { border-color: var(--magenta); background: #fff; }
        .props-filter-amenities { display: flex; flex-wrap: wrap; gap: 8px; }
        .props-amenity-chip {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 8px 14px; border: 1px solid var(--line); border-radius: 999px;
            font-size: 13px; font-weight: 500; cursor: pointer; user-select: none;
            background: var(--bg); transition: background .12s, border-color .12s, color .12s;
        }
        .props-amenity-chip:hover { border-color: var(--purple); }
        .props-amenity-chip input { display: none; }
        .props-amenity-chip.is-on {
            background: #f5f3ff; border-color: var(--purple); color: var(--purple);
        }
        .props-filter-actions {
            display: flex; align-items: center; gap: 14px; justify-self: end;
        }
        .props-filter-apply {
            background: var(--gradient); color: #fff; border: 0;
            padding: 10px 22px; border-radius: 999px;
            font-family: 'Geist', sans-serif; font-weight: 600; font-size: 14px;
            cursor: pointer; transition: transform .12s ease;
        }
        .props-filter-apply:hover { transform: translateY(-1px); }
        .props-filter-clear { color: var(--muted); font-size: 13px; }

        /* ── Two-column results + map (Vrbo-style) ─────────────────── */
        .props-with-map { display: grid; gap: 28px; grid-template-columns: 1fr; }
        @media (min-width: 1080px) {
            .props-with-map { grid-template-columns: minmax(0, 1fr) 420px; align-items: start; }
        }
        .props-results-col { min-width: 0; }
        .props-map-col { display: none; }
        @media (min-width: 1080px) { .props-map-col { display: block; } }

        .props-map-sticky {
            position: sticky; top: 84px;
            height: calc(100vh - 120px); max-height: 720px;
            border-radius: 16px; overflow: hidden;
            background: #f5f3ff; border: 1px solid var(--line);
        }
        .vyt-leaflet-map { width: 100%; height: 100%; }

        /* Mobile map toggle */
        .props-map-toggle {
            display: none; position: fixed; bottom: 22px; left: 50%;
            transform: translateX(-50%); z-index: 60;
            background: var(--ink); color: #fff; border: 0;
            padding: 12px 22px; border-radius: 999px;
            font-family: inherit; font-size: 14px; font-weight: 600; cursor: pointer;
            box-shadow: 0 12px 32px -8px rgba(0,0,0,.4);
            display: inline-flex; align-items: center; gap: 8px;
        }
        @media (min-width: 1080px) { .props-map-toggle { display: none !important; } }
        @media (max-width: 1079px) {
            .props-map-col.is-mobile-open { display: block; position: fixed; inset: 0; z-index: 55; padding: 16px; background: rgba(0,0,0,.4); }
            .props-map-col.is-mobile-open .props-map-sticky { position: relative; top: 0; height: calc(100vh - 90px); max-height: none; }
        }

        /* Custom Leaflet pin look */
        .vyt-leaflet-map .leaflet-popup-content-wrapper { border-radius: 12px; padding: 0; overflow: hidden; }
        .vyt-leaflet-map .leaflet-popup-content { margin: 0; width: 220px; font-family: 'Geist', sans-serif; }
        .vyt-popup-img { width: 100%; height: 120px; object-fit: cover; display: block; background: #f5f3ff; }
        .vyt-popup-body { padding: 10px 14px 14px; }
        .vyt-popup-title { font-family: 'Fraunces', serif; font-weight: 600; font-size: 14px; margin: 0 0 4px; line-height: 1.25; color: var(--ink); }
        .vyt-popup-loc { font-size: 12px; color: var(--muted); margin: 0 0 8px; }
        .vyt-popup-price { font-family: 'Fraunces', serif; font-weight: 600; font-size: 15px; }
        .vyt-popup-price small { color: var(--muted); font-weight: 400; font-size: 12px; }
        .vyt-price-pin {
            background: #fff; border: 2px solid var(--ink);
            color: var(--ink); padding: 4px 10px; border-radius: 999px;
            font-weight: 600; font-size: 12px; font-family: 'Geist', sans-serif;
            white-space: nowrap; box-shadow: 0 2px 6px rgba(0,0,0,.18);
            transition: transform .12s ease, background .12s, color .12s, border-color .12s;
        }
        .vyt-price-pin.is-active {
            background: var(--ink); color: #fff; transform: scale(1.08);
        }
        .props-card.is-map-hover { box-shadow: 0 12px 32px -12px rgba(123,44,191,.28); transform: translateY(-2px); }
    </style>
    @include('partials.search-bar-styles')
    {{-- Leaflet CSS via CDN — pinned version for cache stability --}}
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
          crossorigin="anonymous" />
</head>
<body>
    @include('partials.top-nav', ['current' => $current ?? 'stay'])

    <main class="props-shell">
        @yield('content')
    </main>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
            crossorigin="anonymous" defer></script>
    <script src="/vyt-search.js" defer></script>
    <script src="/vyt-properties-map.js" defer></script>
    <script src="/vyt-carousel.js" defer></script>
    <script src="/vyt-track.js" defer></script>
</body>
</html>
