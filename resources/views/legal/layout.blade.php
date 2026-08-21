<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · Vaytoven</title>
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Serif+4:opsz,wght@8..60,400;8..60,500;8..60,600;8..60,700&family=Geist:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
        .legal-eyebrow { font-size: 12px; letter-spacing: .12em; text-transform: uppercase; color: var(--muted); font-weight: 600; }
        .legal-title { font-family: 'Source Serif 4', serif; font-size: clamp(30px, 4vw, 44px); font-weight: 600; letter-spacing: -.02em; margin: 10px 0 8px; }
        .legal-meta { font-size: 13px; color: var(--muted); margin-bottom: 28px; }
        .legal-shell h2 { font-family: 'Source Serif 4', serif; font-size: 22px; font-weight: 600; margin: 32px 0 8px; }
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
        <div class="legal-eyebrow">@yield('eyebrow', 'Legal')</div>
        <h1 class="legal-title">@yield('title')</h1>
        <p class="legal-meta">Effective @yield('effective_date'). Version @yield('version_label').</p>

        @yield('content')
    </main>
</body>
</html>
