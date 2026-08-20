<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sign in') · Vaytoven</title>
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Geist:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --pink:#FF3D8A; --magenta:#D63384; --purple:#7B2CBF;
            --gradient: linear-gradient(135deg,#FF3D8A 0%,#D63384 50%,#7B2CBF 100%);
            --ink:#1A1426; --paper:#FBF8F3; --line:rgba(26,20,38,.10); --muted:rgba(26,20,38,.62);
        }
        * { box-sizing:border-box; }
        html, body { margin:0; padding:0; }
        body {
            font-family:'Geist',ui-sans-serif,system-ui,-apple-system,sans-serif;
            background:var(--paper); color:var(--ink); line-height:1.55;
            min-height:100vh; display:flex; flex-direction:column;
            -webkit-font-smoothing:antialiased;
        }

        /* Brand-colored gradient blobs in the corners — soft, behind the card. */
        .vyt-bg-blob {
            position:fixed; width:520px; height:520px; border-radius:50%;
            filter:blur(120px); opacity:0.35; pointer-events:none; z-index:0;
        }
        .vyt-bg-blob.tl { top:-200px; left:-200px; background:var(--pink); }
        .vyt-bg-blob.br { bottom:-200px; right:-200px; background:var(--purple); }

        .vyt-auth-shell {
            flex:1; display:flex; align-items:center; justify-content:center;
            padding:48px 24px; position:relative; z-index:1;
        }

        .vyt-auth-card {
            width:100%; max-width:440px;
            background:#fff; border:1px solid var(--line); border-radius:18px;
            box-shadow:0 30px 80px -30px rgba(123,44,191,0.20);
            padding:36px 36px 32px;
        }
        /* Registration carries six fields; a wider card keeps the name row
           side-by-side instead of stacking on a laptop. */
        .vyt-auth-card.is-wide { max-width:560px; }
        @media (max-width:520px) { .vyt-auth-card { padding:26px 22px 24px; } }

        /* Paired fields (First / Last). Collapses to one column on phones,
           which is why the grid lives here rather than in the page. */
        .vyt-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:0 16px; }
        @media (max-width:520px) { .vyt-grid-2 { grid-template-columns:1fr; } }

        /* Brand row at the top of the card. */
        .vyt-auth-brand {
            display:flex; align-items:center; gap:10px; margin-bottom:24px;
        }
        .vyt-auth-brand svg { width:32px; height:32px; flex-shrink:0; }
        .vyt-auth-brand .wordmark {
            font-family:'Fraunces',serif; font-weight:600; font-size:22px;
            letter-spacing:-0.01em;
        }
        .vyt-auth-brand .tag {
            margin-left:auto; font-size:10px; letter-spacing:.18em;
            text-transform:uppercase; color:var(--muted); font-weight:600;
        }

        .vyt-auth-h1 {
            font-family:'Fraunces',serif; font-weight:600; font-size:28px;
            line-height:1.15; letter-spacing:-0.02em; margin:0 0 6px;
        }
        .vyt-auth-sub { color:var(--muted); font-size:14.5px; margin:0 0 26px; }

        .vyt-field { margin-bottom:16px; }
        .vyt-field label {
            display:block; font-size:12px; font-weight:600;
            letter-spacing:.06em; text-transform:uppercase;
            color:var(--muted); margin-bottom:6px;
        }
        .vyt-field input {
            width:100%; padding:12px 14px;
            border:1px solid var(--line); border-radius:10px;
            font:inherit; font-size:15px; background:#fff;
            transition:border-color .12s, box-shadow .12s;
        }
        .vyt-field input:focus {
            outline:0; border-color:var(--magenta);
            box-shadow:0 0 0 3px rgba(255,61,138,.15);
        }
        .vyt-field-error { color:#b91c1c; font-size:12.5px; margin-top:6px; }

        .vyt-row-between {
            display:flex; align-items:center; justify-content:space-between;
            margin:6px 0 22px; font-size:13.5px;
        }
        .vyt-row-between label { color:var(--muted); display:flex; align-items:center; gap:6px; cursor:pointer; }

        .vyt-auth-cta {
            width:100%; background:var(--gradient); color:#fff; border:0;
            padding:13px 18px; border-radius:999px; font:inherit; font-weight:600;
            font-size:15px; cursor:pointer; transition:transform .12s;
        }
        .vyt-auth-cta:hover { transform:translateY(-1px); }
        .vyt-auth-cta.is-caps { letter-spacing:.08em; text-transform:uppercase; font-size:13.5px; }
        .vyt-auth-cta:focus-visible { outline:2px solid var(--purple); outline-offset:3px; }

        /* Consent / helper text under a field group. */
        .vyt-consent {
            display:flex; align-items:flex-start; gap:9px;
            font-size:13px; color:var(--muted); line-height:1.5;
            margin:4px 0 20px;
        }
        .vyt-consent input[type=checkbox] {
            margin-top:3px; width:16px; height:16px; accent-color:#D63384; flex-shrink:0;
        }
        .vyt-consent a { color:var(--magenta); font-weight:500; }

        .vyt-hint { font-size:12px; color:var(--muted); margin-top:6px; }

        .vyt-auth-foot {
            margin-top:22px; padding-top:18px; border-top:1px solid var(--line);
            font-size:13.5px; color:var(--muted); text-align:center;
        }
        .vyt-auth-foot a { color:var(--magenta); text-decoration:none; font-weight:500; }
        .vyt-auth-foot a:hover { text-decoration:underline; }

        .vyt-alert-status {
            background:#ecfdf5; color:#047857; border:1px solid #a7f3d0;
            padding:10px 14px; border-radius:10px; font-size:13.5px; margin-bottom:18px;
        }

        .vyt-page-foot {
            padding:18px 24px; text-align:center; font-size:12px; color:var(--muted);
            position:relative; z-index:1;
        }
        .vyt-page-foot a { color:var(--muted); text-decoration:none; margin:0 8px; }
        .vyt-page-foot a:hover { color:var(--ink); }

        a { color:var(--magenta); }
    </style>
</head>
<body>
    <div class="vyt-bg-blob tl"></div>
    <div class="vyt-bg-blob br"></div>

    <main class="vyt-auth-shell">
        <div class="vyt-auth-card @yield('card_class')">
            <div class="vyt-auth-brand">
                <svg viewBox="0 0 64 64">
                    <defs>
                        <linearGradient id="vyt-auth-grad" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0%" stop-color="#FF3D8A"/>
                            <stop offset="50%" stop-color="#D63384"/>
                            <stop offset="100%" stop-color="#7B2CBF"/>
                        </linearGradient>
                    </defs>
                    <path fill="url(#vyt-auth-grad)" d="M10 8h12l10 30 10-30h12L34 56h-8z"/>
                    <circle cx="48" cy="14" r="8" fill="url(#vyt-auth-grad)"/>
                    <circle cx="48" cy="14" r="3" fill="#fff"/>
                </svg>
                <span class="wordmark">Vaytoven</span>
                <span class="tag">@yield('brand_tag', 'Sign in')</span>
            </div>

            @yield('content')
        </div>
    </main>

    <footer class="vyt-page-foot">
        <a href="{{ route('legal.tos') }}">Terms</a>·
        <a href="{{ route('legal.privacy') }}">Privacy</a>·
        <a href="https://vaytoven.com">vaytoven.com</a>
    </footer>
</body>
</html>
