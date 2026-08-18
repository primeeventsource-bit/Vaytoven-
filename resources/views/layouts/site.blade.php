{{--
  Shared shell for the public company/resource pages.

  The older marketing pages (hosts/show, members/show, signup/show) are each a
  standalone HTML document that repeats the head, fonts, nav and styles. That
  was tolerable for three pages; it is not for fourteen. Everything added from
  here extends this instead, so the nav, footer and brand tokens have one
  definition.
--}}
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · {{ setting('general.site_name', 'Vaytoven') }}</title>
    @include('partials.favicon')
    <meta name="description" content="@yield('meta_description', setting('seo.meta_description_default', 'Curated vacation homes for travelers, hosts, and vacation club members.'))">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Geist:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @include('partials.brand-styles')
    <style>
        .site-shell { max-width: 1120px; margin: 0 auto; padding: 0 24px; }
        .site-hero { padding: 64px 0 40px; }
        .site-hero .eyebrow {
            font-size: 12px; letter-spacing: .16em; text-transform: uppercase;
            color: var(--magenta); font-weight: 700; margin: 0 0 12px;
        }
        .site-hero h1 {
            font-family: 'Fraunces', serif; font-weight: 600;
            font-size: clamp(34px, 5vw, 52px); line-height: 1.08;
            letter-spacing: -.02em; margin: 0 0 14px; text-wrap: balance;
        }
        .site-hero .lede { color: var(--muted); font-size: 17px; max-width: 62ch; margin: 0; }

        .site-section { padding: 0 0 56px; }
        .site-section h2 {
            font-family: 'Fraunces', serif; font-weight: 600; font-size: 26px;
            letter-spacing: -.01em; margin: 0 0 8px; text-wrap: balance;
        }
        .site-section h3 { font-size: 17px; margin: 0 0 6px; }
        .site-section p { color: var(--muted); line-height: 1.65; max-width: 68ch; }

        .site-card {
            background: #fff; border: 1px solid var(--line); border-radius: 14px;
            padding: 22px 24px;
        }
        .site-grid { display: grid; gap: 16px; grid-template-columns: 1fr; }
        @media (min-width: 720px) { .site-grid.cols-2 { grid-template-columns: repeat(2, 1fr); } }
        @media (min-width: 900px) { .site-grid.cols-3 { grid-template-columns: repeat(3, 1fr); } }

        .site-cta {
            display: inline-block; background: var(--gradient); color: #fff;
            padding: 12px 26px; border-radius: 999px; font-weight: 600;
            font-size: 15px; text-decoration: none; border: 0; cursor: pointer;
            font-family: inherit; transition: transform .12s;
        }
        .site-cta:hover { transform: translateY(-1px); text-decoration: none; color: #fff; }
        .site-cta:focus-visible { outline: 2px solid var(--purple); outline-offset: 3px; }

        .site-field { margin-bottom: 16px; }
        .site-field label {
            display: block; font-size: 12px; font-weight: 600; letter-spacing: .06em;
            text-transform: uppercase; color: var(--muted); margin-bottom: 6px;
        }
        .site-field input, .site-field select, .site-field textarea {
            width: 100%; padding: 11px 13px; border: 1px solid var(--line);
            border-radius: 9px; font: inherit; font-size: 15px; background: var(--bg);
            outline: none; font-family: inherit;
        }
        .site-field textarea { resize: vertical; min-height: 130px; }
        .site-field input:focus, .site-field select:focus, .site-field textarea:focus {
            border-color: var(--magenta); background: #fff;
            box-shadow: 0 0 0 3px rgba(255, 61, 138, .14);
        }
        .site-field .err { color: #b91c1c; font-size: 12.5px; margin-top: 5px; }
        .site-row-2 { display: grid; grid-template-columns: 1fr; gap: 0 16px; }
        @media (min-width: 620px) { .site-row-2 { grid-template-columns: 1fr 1fr; } }

        .site-alert {
            background: #ecfdf5; border: 1px solid #a7f3d0; color: #047857;
            padding: 14px 16px; border-radius: 11px; margin-bottom: 22px; font-size: 14.5px;
        }
        .site-alert strong { display: block; margin-bottom: 3px; }
        .site-alert code {
            font-family: 'SFMono-Regular', Consolas, monospace; background: #fff;
            padding: 2px 8px; border-radius: 5px; font-size: 13.5px; letter-spacing: .04em;
        }
        .site-empty {
            text-align: center; padding: 56px 24px; color: var(--muted);
            border: 1px dashed var(--line); border-radius: 14px; font-size: 15px;
        }
        .site-note {
            font-size: 12.5px; color: var(--muted); line-height: 1.6;
            border-left: 3px solid var(--line); padding-left: 14px; margin-top: 22px;
        }
    </style>
    @include('partials.footer-styles')
    @stack('head')
</head>
<body>
    @include('partials.top-nav', ['current' => trim($navCurrent ?? '')])

    <main>
        @yield('content')
    </main>

    @include('partials.site-footer')

    <script src="/vyt-track.js" defer></script>
    @stack('scripts')
</body>
</html>
