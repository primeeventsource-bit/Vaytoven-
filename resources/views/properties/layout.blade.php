<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Stays') · Vaytoven</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Geist:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
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
        .props-pagination { margin-top:32px; display:flex; justify-content:center; gap:6px; }
        .props-pagination a, .props-pagination span {
            padding:8px 14px; border-radius:8px; font-size:13px; font-weight:500;
            background:#fff; border:1px solid var(--line); color:var(--ink);
        }
        .props-pagination .active span, .props-pagination .active { background: var(--gradient); color:#fff; border-color: transparent; }

        /* Detail view */
        .props-detail-back { font-size:13px; color:var(--muted); }
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
    </style>
    @include('partials.search-bar-styles')
</head>
<body>
    @include('partials.top-nav', ['current' => $current ?? 'stay'])

    <main class="props-shell">
        @yield('content')
    </main>

    <script src="/vyt-search.js" defer></script>
    <script src="/vyt-track.js" defer></script>
</body>
</html>
