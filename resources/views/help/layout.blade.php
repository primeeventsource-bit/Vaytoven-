<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Help center') · Vaytoven</title>
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
        body { margin:0; font-family: 'Geist', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: var(--ink); background: var(--bg); line-height: 1.55; }
        a { color: var(--purple); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .help-nav { background:#fff; border-bottom: 1px solid var(--line); padding: 18px 24px; display: flex; align-items: center; gap: 16px; }
        .help-nav .brand { display:flex; align-items: center; gap: 10px; font-weight: 600; font-size: 18px; color: var(--ink); }
        .help-nav .brand svg { width: 28px; height: 28px; }
        .help-nav .home-link { margin-left: auto; font-size: 13px; }
        .help-shell { max-width: 920px; margin: 0 auto; padding: 40px 24px 80px; }
        .help-eyebrow { font-size: 12px; letter-spacing: .12em; text-transform: uppercase; color: var(--muted); font-weight: 600; }
        .help-title { font-family: 'Fraunces', serif; font-size: clamp(34px, 5vw, 52px); font-weight: 600; letter-spacing: -.02em; margin: 12px 0 24px; }
        .help-search { position: relative; margin-bottom: 36px; }
        .help-search input { width: 100%; padding: 14px 18px 14px 44px; font-size: 16px; border: 1px solid var(--line); border-radius: 12px; background: #fff; outline: none; }
        .help-search input:focus { border-color: var(--magenta); box-shadow: 0 0 0 3px rgba(214,51,132,.12); }
        .help-search svg { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); width: 18px; height: 18px; stroke: var(--muted); fill: none; stroke-width: 2; }
        .help-results { background:#fff; border:1px solid var(--line); border-radius: 12px; margin-top:8px; overflow:hidden; }
        .help-result { display:block; padding: 12px 16px; border-top: 1px solid var(--line); color: var(--ink); }
        .help-result:first-child { border-top: 0; }
        .help-result:hover { background: #faf7ff; text-decoration: none; }
        .help-result-title { font-weight: 600; }
        .help-result-summary { font-size: 13px; color: var(--muted); margin-top: 4px; }
        .help-audiences { display: flex; gap: 8px; margin: 0 0 28px; flex-wrap: wrap; }
        .help-audience-chip { padding: 6px 14px; border-radius: 999px; background:#fff; border:1px solid var(--line); font-size: 13px; font-weight: 500; color: var(--ink); }
        .help-audience-chip.is-active { background: var(--gradient); color: #fff; border-color: transparent; }
        .help-category { margin-bottom: 32px; }
        .help-category h2 { font-family: 'Fraunces', serif; font-size: 22px; font-weight: 600; margin: 0 0 14px; }
        .help-category ul { list-style:none; padding:0; margin:0; background:#fff; border:1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .help-category li { border-top: 1px solid var(--line); }
        .help-category li:first-child { border-top: 0; }
        .help-category li a { display:block; padding: 14px 16px; color: var(--ink); }
        .help-category li a:hover { background: #faf7ff; text-decoration: none; }
        .help-category li .summary { display:block; font-size: 13px; color: var(--muted); margin-top: 4px; }
        .help-article { background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 32px; }
        .help-article-back { font-size: 13px; color: var(--muted); }
        .help-article h1 { font-family: 'Fraunces', serif; font-size: clamp(28px, 3vw, 36px); font-weight: 600; margin: 16px 0 16px; line-height: 1.2; }
        .help-article-summary { font-size: 17px; color: var(--ink); margin: 0 0 24px; padding-bottom: 24px; border-bottom: 1px solid var(--line); }
        .help-article-body { font-size: 15.5px; }
        .help-article-body p { margin: 0 0 16px; }
        .help-article-meta { font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: .08em; }
    </style>
    <script src="/vyt-track.js" defer></script>
</head>
<body>
    @include('partials.top-nav')
    <main class="help-shell">
        @yield('content')
    </main>
</body>
</html>
