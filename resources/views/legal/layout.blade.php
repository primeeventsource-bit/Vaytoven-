<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · Vaytoven</title>
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
        body { margin:0; font-family: 'Geist', -apple-system, sans-serif; color: var(--ink); background: var(--bg); line-height: 1.65; }
        a { color: var(--purple); }
        .legal-nav { background:#fff; border-bottom: 1px solid var(--line); padding: 18px 24px; display: flex; align-items: center; gap: 16px; }
        .legal-nav .brand { display:flex; align-items: center; gap: 10px; font-weight: 600; color: var(--ink); text-decoration: none; }
        .legal-nav .brand svg { width: 28px; height: 28px; }
        .legal-nav .home-link { margin-left: auto; font-size: 13px; }
        .legal-shell { max-width: 760px; margin: 0 auto; padding: 32px 24px 80px; }
        .draft-banner {
            background:#fff7ed; border:1px solid #fdba74; color:#9a3412;
            padding: 12px 16px; border-radius: 10px; font-size: 13px;
            margin-bottom: 28px;
        }
        .legal-eyebrow { font-size: 12px; letter-spacing: .12em; text-transform: uppercase; color: var(--muted); font-weight: 600; }
        .legal-title { font-family: 'Fraunces', serif; font-size: clamp(30px, 4vw, 44px); font-weight: 600; letter-spacing: -.02em; margin: 10px 0 8px; }
        .legal-meta { font-size: 13px; color: var(--muted); margin-bottom: 28px; }
        .legal-shell h2 { font-family: 'Fraunces', serif; font-size: 22px; font-weight: 600; margin: 32px 0 8px; }
        .legal-shell h3 { font-size: 16px; font-weight: 600; margin: 18px 0 6px; }
        .legal-shell p { margin: 0 0 14px; font-size: 15px; }
        .legal-shell ul { padding-left: 22px; margin: 0 0 14px; }
        .legal-shell li { margin-bottom: 6px; }
        .legal-toc { background:#fff; border:1px solid var(--line); border-radius: 12px; padding: 16px 20px; margin-bottom: 28px; }
        .legal-toc strong { display:block; font-size: 12px; letter-spacing: .12em; text-transform: uppercase; color: var(--muted); margin-bottom: 10px; }
        .legal-toc a { display:block; padding: 6px 0; font-size: 14px; }
    </style>
</head>
<body>
    @include('partials.top-nav')
    <main class="legal-shell">
        <div class="draft-banner">
            <strong>DRAFT — v1 placeholder.</strong> This document is a working draft and has not been reviewed by counsel. The legally binding version will replace this text before public launch; the URL and document hash will not change so prior acceptances remain auditable.
        </div>

        <div class="legal-eyebrow">@yield('eyebrow', 'Legal')</div>
        <h1 class="legal-title">@yield('title')</h1>
        <p class="legal-meta">Effective @yield('effective_date'). Version @yield('version_label').</p>

        @yield('content')
    </main>
</body>
</html>
