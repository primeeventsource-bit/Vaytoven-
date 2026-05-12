<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · Vaytoven</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Geist:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --pink:#FF3D8A; --magenta:#D63384; --purple:#7B2CBF;
            --gradient: linear-gradient(135deg,#FF3D8A 0%,#D63384 50%,#7B2CBF 100%);
            --ink:#1d1f21; --muted:#6b7280; --line:#e7e5e4; --bg:#fafaf9;
            --tile-pink:#fdf2f8; --tile-purple:#f5f3ff; --tile-amber:#fffbeb;
            --tile-red:#fef2f2; --tile-emerald:#ecfdf5; --tile-blue:#eff6ff; --tile-slate:#f1f5f9;
        }
        * { box-sizing: border-box; }
        body { margin:0; font-family:'Geist',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; color: var(--ink); background: var(--bg); line-height: 1.55; -webkit-font-smoothing: antialiased; }
        a { color: var(--purple); text-decoration: none; }
        a:hover { text-decoration: underline; }

        /* Top nav */
        .vyt-nav {
            background:#fff; border-bottom:1px solid var(--line);
            padding: 14px 24px; display:flex; align-items:center; gap:18px;
            position: sticky; top: 0; z-index: 30;
        }
        .vyt-nav .brand { display:flex; align-items:center; gap:10px; color:var(--ink); font-weight:600; font-size:16px; text-decoration:none; }
        .vyt-nav .brand svg { width:30px; height:30px; }
        .vyt-nav-links { display:flex; gap:18px; margin-left:24px; }
        .vyt-nav-links a { color:var(--ink); font-size:14px; font-weight:500; }
        .vyt-nav-links a:hover { color: var(--purple); text-decoration:none; }
        .vyt-nav-spacer { flex: 1; }
        .vyt-nav-user {
            display:flex; align-items:center; gap:10px; padding:6px 12px;
            background:var(--bg); border-radius:999px; font-size:13px;
        }
        .vyt-nav-user .role-chip {
            display:inline-block; padding:2px 10px; border-radius:999px;
            font-size:11px; font-weight:600; letter-spacing:.04em; text-transform:uppercase;
            color:#fff; background: var(--gradient);
        }
        .vyt-logout-form { margin: 0; }
        .vyt-logout-btn {
            background: none; border: 0; padding: 0; margin: 0;
            color: var(--muted); font: inherit; cursor: pointer;
            font-size: 13px;
        }
        .vyt-logout-btn:hover { color: var(--purple); }

        /* Page header */
        .vyt-pageheader {
            background:#fff; border-bottom:1px solid var(--line);
            padding: 28px 24px;
        }
        .vyt-pageheader-inner { max-width: 1200px; margin: 0 auto; display:flex; align-items:flex-end; justify-content:space-between; gap:16px; flex-wrap:wrap; }
        .vyt-pageheader h1 {
            font-family:'Fraunces',serif; font-size: clamp(28px, 3vw, 36px);
            font-weight:600; letter-spacing:-.02em; margin: 4px 0 0;
        }
        .vyt-eyebrow { font-size:12px; letter-spacing:.12em; text-transform:uppercase; color:var(--muted); font-weight:600; }

        /* Shell */
        .vyt-shell { max-width:1200px; margin:0 auto; padding: 28px 24px 80px; }
        .vyt-section { margin-bottom: 32px; }
        .vyt-section-header { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:14px; }
        .vyt-section-header h2 {
            font-family:'Fraunces',serif; font-size: 20px; font-weight:600;
            margin: 0; letter-spacing: -.005em;
        }
        .vyt-section-meta { font-size:12px; color:var(--muted); text-transform:uppercase; letter-spacing:.06em; }

        /* Tiles */
        .vyt-tiles { display:grid; gap: 14px; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
        .vyt-tile {
            background:#fff; border: 1px solid var(--line); border-radius: 14px;
            padding: 16px 18px; transition: transform .12s ease, box-shadow .15s ease;
            display: block; color: var(--ink); text-decoration:none;
        }
        a.vyt-tile:hover { transform: translateY(-2px); box-shadow: 0 6px 20px -8px rgba(123,44,191,.15); text-decoration: none; }
        .vyt-tile-label { font-size:11px; letter-spacing:.1em; text-transform:uppercase; color:var(--muted); font-weight:600; }
        .vyt-tile-value {
            display:inline-block; margin-top:10px;
            font-family:'Fraunces', serif; font-size: 28px; font-weight:600;
            line-height: 1; letter-spacing: -.01em;
            padding: 4px 12px; border-radius: 8px;
        }
        .vyt-tile-value.t-pink    { background: var(--tile-pink);    color: var(--magenta); }
        .vyt-tile-value.t-purple  { background: var(--tile-purple);  color: var(--purple); }
        .vyt-tile-value.t-amber   { background: var(--tile-amber);   color:#92400e; }
        .vyt-tile-value.t-red     { background: var(--tile-red);     color:#b91c1c; }
        .vyt-tile-value.t-emerald { background: var(--tile-emerald); color:#047857; }
        .vyt-tile-value.t-blue    { background: var(--tile-blue);    color:#1d4ed8; }
        .vyt-tile-value.t-slate   { background: var(--tile-slate);   color: var(--ink); }
        .vyt-tile-value.t-gradient { background: var(--gradient); color: #fff; }

        /* Cards (general) */
        .vyt-card {
            background:#fff; border:1px solid var(--line); border-radius: 14px;
            overflow: hidden;
        }
        .vyt-card-header {
            padding: 16px 22px; border-bottom: 1px solid var(--line);
            display:flex; justify-content:space-between; align-items:center;
        }
        .vyt-card-header h3 { font-family:'Fraunces',serif; font-size:17px; font-weight:600; margin:0; }
        .vyt-card-body { padding: 18px 22px; }
        .vyt-card-empty { padding: 32px 22px; color: var(--muted); font-size: 14px; text-align:center; }

        /* Tables */
        .vyt-table { width:100%; border-collapse: collapse; font-size: 13.5px; }
        .vyt-table thead { background: #fafaf9; }
        .vyt-table th {
            text-align:left; padding: 10px 22px; font-size: 11px;
            text-transform:uppercase; letter-spacing:.08em; color:var(--muted); font-weight: 600;
        }
        .vyt-table td { padding: 12px 22px; border-top: 1px solid var(--line); vertical-align: middle; }
        .vyt-table tr:hover td { background: #fcfaff; }
        .vyt-mono { font-family:'SFMono-Regular', Consolas, 'Liberation Mono', monospace; font-size: 12.5px; color: var(--purple); font-weight: 600; }
        .vyt-pill {
            display:inline-block; padding: 2px 10px; border-radius: 999px;
            background:#f5f3ff; color: var(--purple); font-size:11px; font-weight:600;
            letter-spacing:.02em; text-transform: capitalize;
        }
        .vyt-faint { color: var(--muted); font-size: 12px; }

        /* Two-column row */
        .vyt-row-2 { display: grid; gap: 18px; grid-template-columns: 1fr; }
        @media (min-width: 760px) { .vyt-row-2 { grid-template-columns: 1fr 1fr; } }

        /* Plain key/value list */
        .vyt-kv { list-style: none; padding: 0; margin: 0; }
        .vyt-kv li {
            display:flex; justify-content: space-between; align-items: center;
            padding: 10px 0; border-top: 1px solid var(--line);
            font-size: 14px;
        }
        .vyt-kv li:first-child { border-top: 0; }
        .vyt-kv .k { color: var(--ink); text-transform: capitalize; }
        .vyt-kv .v { font-weight: 600; }
        .vyt-kv .v.danger { color:#b91c1c; }
        .vyt-kv .h { font-family:'SFMono-Regular',Consolas,monospace; font-size: 11.5px; color: var(--muted); }
    </style>
    @stack('head')
</head>
<body>
    @include('partials.top-nav')

    <header class="vyt-pageheader">
        <div class="vyt-pageheader-inner">
            <div>
                <div class="vyt-eyebrow">@yield('eyebrow', 'Dashboard')</div>
                <h1>@yield('title', 'Dashboard')</h1>
            </div>
            @hasSection('header_meta')
                <div class="vyt-faint">@yield('header_meta')</div>
            @endif
        </div>
    </header>

    <main class="vyt-shell">
        @if (session('success'))
            <div style="background:#ecfdf5;color:#047857;border:1px solid #a7f3d0;padding:12px 16px;border-radius:10px;margin-bottom:18px;font-size:14px;">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div style="background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5;padding:12px 16px;border-radius:10px;margin-bottom:18px;font-size:14px;">
                {{ session('error') }}
            </div>
        @endif
        @yield('content')
    </main>
    @stack('scripts')
</body>
</html>
