<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your $300 Dining Rewards · Vaytoven</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,400;0,500;1,400&family=Geist:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --pink:#FF3D8A; --magenta:#D63384; --purple:#7B2CBF; --ink:#1A1426; --paper:#FBF8F3; --muted:#6b6478; --line:#e7e2da; }
        * { box-sizing: border-box; }
        body { margin:0; background:var(--paper); color:var(--ink); font-family:Geist, system-ui, sans-serif; }
        .wrap { max-width: 900px; margin: 0 auto; background: var(--paper); }
        .top { display:flex; justify-content:space-between; align-items:center; padding:28px 32px; border-bottom:1px solid var(--line); gap:12px; flex-wrap:wrap; }
        .brand { display:flex; align-items:center; gap:10px; font-family:Fraunces, Georgia, serif; font-size:30px; }
        .program { font-size:12px; letter-spacing:.14em; font-weight:600; color:var(--muted); text-transform:uppercase; }
        .tag { text-align:center; padding:22px 16px; font-family:Fraunces, Georgia, serif; font-style:italic; font-size:21px; color:#4a4458; border-bottom:1px solid var(--line); }
        .tag b { color:var(--magenta); font-weight:600; }
        .hero { background:linear-gradient(135deg, #FF3D8A 0%, #e85aa0 55%, #D63384 100%); color:#fff; display:flex; align-items:center; gap:40px; padding:56px 40px; flex-wrap:wrap; }
        .hero svg { flex:0 0 auto; }
        .eyebrow { font-size:13px; letter-spacing:.2em; font-weight:600; text-transform:uppercase; opacity:.95; }
        .amount { font-family:Fraunces, Georgia, serif; font-size:84px; line-height:1; margin:10px 0 4px; }
        .amount-sub { font-family:Fraunces, Georgia, serif; font-size:30px; }
        .meals { margin-top:10px; font-size:17px; opacity:.95; }
        .body { padding:36px 40px 12px; }
        h2 { font-family:Fraunces, Georgia, serif; font-weight:400; font-size:28px; margin:0 0 14px; }
        ul { list-style:none; margin:0; padding:0; }
        li { padding:13px 0 13px 22px; border-bottom:1px solid var(--line); position:relative; font-size:16px; color:#3f3a4b; }
        li:last-child { border-bottom:0; }
        li::before { content:''; position:absolute; left:2px; top:20px; width:8px; height:8px; border-radius:50%; background:var(--magenta); }
        .cta { margin:24px 40px 28px; border:2px solid var(--magenta); border-radius:16px; background:#fff5f9; padding:24px; text-align:center; }
        .cta p { margin:0 0 16px; font-size:15px; color:#4a4458; }
        .buttons { display:flex; gap:12px; justify-content:center; flex-wrap:wrap; }
        .buttons button { border:0; border-radius:10px; padding:14px 22px; font-weight:700; font-size:15px; letter-spacing:.04em; cursor:pointer; font-family:inherit; }
        .primary { color:#fff; background:linear-gradient(135deg, #FF3D8A 0%, #D63384 45%, #7B2CBF 100%); }
        .secondary { color:var(--magenta); background:#fff; border:2px solid var(--magenta) !important; }
        .fine { padding:0 40px 36px; font-size:12px; color:var(--muted); text-align:center; line-height:1.55; }
        @media (max-width: 600px) {
            .top, .body { padding-left:16px; padding-right:16px; }
            .hero { padding:36px 16px; gap:20px; }
            .amount { font-size:64px; }
            .cta { margin:20px 16px; }
            .fine { padding:0 16px 28px; }
            .buttons button { width:100%; }
        }
    </style>
</head>
<body>
@php($I = $incentive)
<div class="wrap">
    <div class="top">
        <div class="brand">
            <svg width="30" height="34" viewBox="0 0 30 34" aria-hidden="true">
                <defs><linearGradient id="vg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#FF3D8A"/><stop offset=".5" stop-color="#D63384"/><stop offset="1" stop-color="#7B2CBF"/></linearGradient></defs>
                <path d="M2 4h8l5 18 5-18h8L19 32h-8z" fill="url(#vg)"/>
                <circle cx="25" cy="5" r="4" fill="url(#vg)"/>
            </svg>
            Vaytoven
        </div>
        <div class="program">Managed Listing Program</div>
    </div>

    <div class="tag">Put your vacation property in tune with <b>Vaytoven</b>’s Listing Program</div>

    <div class="hero">
        <svg width="140" height="130" viewBox="0 0 140 130" fill="none" stroke="#fff" stroke-width="2.5" aria-hidden="true">
            <path d="M30 30h46c0 26-10 40-23 40S30 56 30 30z"/>
            <path d="M53 70v42M36 114h34"/>
            <path d="M62 26l30 12c-4 24-17 34-29 30"/>
            <path d="M80 68l18 38M86 116l30-12"/>
            <path d="M40 12l2 6 6 2-6 2-2 6-2-6-6-2 6-2z" fill="#fff" stroke="none"/>
            <circle cx="12" cy="20" r="1.6" fill="#fff" stroke="none"/><circle cx="118" cy="36" r="1.6" fill="#fff" stroke="none"/><circle cx="8" cy="78" r="1.3" fill="#fff" stroke="none"/>
        </svg>
        <div>
            <div class="eyebrow">Our thank-you when you enroll</div>
            <div class="amount">{{ $I::VALUE }}</div>
            <div class="amount-sub">in Dining Rewards</div>
            <div class="meals">Breakfast · Lunch · Dinner</div>
        </div>
    </div>

    <div class="body">
        <h2>What’s included</h2>
        <ul>
            @foreach ($I::INCLUDED as $line)
                <li>{{ $line }}</li>
            @endforeach
        </ul>
    </div>

    <form method="POST" action="{{ route('member.incentive.acknowledge') }}" class="cta">
        @csrf
        <p>Thank you for enrolling in the Vaytoven Managed Listing Program. Your advertisement is waiting in your dashboard.</p>
        <div class="buttons">
            <button type="submit" name="choice" value="view" class="secondary">{{ $I::VIEW_BUTTON }}</button>
            <button type="submit" name="choice" value="continue" class="primary">{{ $I::CONTINUE_BUTTON }}</button>
        </div>
    </form>

    <div class="fine">
        <strong>Certificate fulfilled by {{ $I::PROVIDER }}</strong>{{ \Illuminate\Support\Str::after($I::FINE_PRINT, 'Certificate fulfilled by '.$I::PROVIDER) }}
    </div>
</div>
</body>
</html>
