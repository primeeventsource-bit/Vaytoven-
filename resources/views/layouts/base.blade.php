<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Vaytoven')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,500;0,600;1,500&family=Geist:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --pink: #FF3D8A; --magenta: #D63384; --purple: #7B2CBF;
            --ink: #1A1426; --paper: #FBF8F3; --line: rgba(26,20,38,.10); --muted: rgba(26,20,38,.62);
            --gradient: linear-gradient(135deg, #FF3D8A 0%, #D63384 45%, #7B2CBF 100%);
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body { font-family: 'Geist', system-ui, sans-serif; background: var(--paper); color: var(--ink); line-height: 1.55; }
        .display { font-family: 'Fraunces', Georgia, serif; font-weight: 600; line-height: 1.1; }
        a { color: var(--magenta); }
        .topbar { background: #fff; border-bottom: 1px solid var(--line); padding: 14px 32px; display: flex; align-items: center; justify-content: space-between; }
        .topbar .brand { font-family: 'Fraunces', serif; font-weight: 600; font-size: 20px; }
        .topbar .brand span { background: var(--gradient); -webkit-background-clip: text; color: transparent; }
        .topbar nav { display: flex; gap: 22px; font-size: 14px; }
        .topbar nav a { color: var(--muted); text-decoration: none; }
        .topbar nav a:hover, .topbar nav a.active { color: var(--ink); }
        .container { max-width: 1180px; margin: 0 auto; padding: 32px; }
        h1 { font-family: 'Fraunces', serif; font-weight: 600; font-size: 36px; margin: 0 0 24px; }
        h2 { font-family: 'Fraunces', serif; font-weight: 600; font-size: 22px; margin: 24px 0 12px; }
        .card { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 22px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid var(--line); }
        th { font-weight: 600; font-size: 12px; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); }
        tr:last-child td { border-bottom: 0; }
        .pill { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; background: var(--paper); color: var(--ink); }
        .pill-sent { background: #FFF6E5; color: #B66A00; }
        .pill-delivered, .pill-viewed { background: #EDE7FF; color: #5B2CBF; }
        .pill-signed, .pill-completed { background: #E5F8EE; color: #1F7A4D; }
        .pill-declined, .pill-voided, .pill-expired { background: #FCE7EE; color: var(--magenta); }
        .btn { display: inline-block; padding: 10px 18px; border-radius: 999px; font-size: 14px; font-weight: 600; cursor: pointer; border: 0; text-decoration: none; }
        .btn-primary { background: var(--gradient); color: #fff; }
        .btn-secondary { background: var(--paper); color: var(--ink); border: 1px solid var(--line); }
        .btn-danger { background: #FCE7EE; color: var(--magenta); border: 1px solid rgba(214,51,132,.2); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px; }
        .form-field { display: flex; flex-direction: column; }
        .form-field label { font-size: 12px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); margin-bottom: 6px; }
        .form-field input, .form-field select, .form-field textarea {
            padding: 11px 14px; border: 1px solid var(--line); border-radius: 10px;
            font: inherit; font-size: 15px; background: #fff;
        }
        .form-field input:focus, .form-field select:focus, .form-field textarea:focus {
            outline: 0; border-color: var(--pink); box-shadow: 0 0 0 3px rgba(255,61,138,.15);
        }
        .alert { padding: 12px 16px; border-radius: 10px; margin-bottom: 18px; font-size: 14px; }
        .alert-success { background: #E5F8EE; color: #1F7A4D; }
        .alert-error { background: #FCE7EE; color: var(--magenta); }
        .toolbar { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; margin-bottom: 18px; }
        .toolbar input, .toolbar select { padding: 9px 12px; border: 1px solid var(--line); border-radius: 10px; font: inherit; font-size: 14px; background: #fff; }
        @media (max-width: 720px) {
            .topbar { padding: 12px 16px; }
            .topbar nav { display: none; }
            .container { padding: 20px 16px; }
            .form-row { grid-template-columns: 1fr; }
            table { font-size: 13px; }
        }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="brand"><span>Vaytoven</span> &middot; @yield('section', 'Console')</div>
        <nav>@yield('nav')</nav>
    </div>

    <main class="container">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-error">
                <strong>Please fix:</strong>
                <ul style="margin:6px 0 0; padding-left:18px;">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
