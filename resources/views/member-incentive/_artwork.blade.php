{{-- One offer, as its artwork. Expects $offer (App\Services\Fulfillment\Incentive).
     Styles are scoped under .vy-offer so it can sit inside any layout. --}}
@once
<style>
    .vy-offer { --pink:#FF3D8A; --magenta:#D63384; --purple:#7B2CBF; --ink:#1A1426; --paper:#FBF8F3; --muted:#6b6478; --line:#e7e2da;
        background:var(--paper); color:var(--ink); font-family:Geist, system-ui, sans-serif; }
    .vy-offer .top { display:flex; justify-content:space-between; align-items:center; padding:28px 32px; border-bottom:1px solid var(--line); gap:12px; flex-wrap:wrap; }
    .vy-offer .brand { display:flex; align-items:center; gap:10px; font-family:Fraunces, Georgia, serif; font-size:30px; }
    .vy-offer .program { font-size:12px; letter-spacing:.14em; font-weight:600; color:var(--muted); text-transform:uppercase; }
    .vy-offer .tag { text-align:center; padding:22px 16px; font-family:Fraunces, Georgia, serif; font-style:italic; font-size:21px; color:#4a4458; border-bottom:1px solid var(--line); }
    .vy-offer .tag b { color:var(--magenta); font-weight:600; }
    .vy-offer .hero { color:#fff; display:flex; align-items:center; gap:40px; padding:56px 40px; flex-wrap:wrap; }
    .vy-offer .hero svg { flex:0 0 auto; }
    .vy-offer .eyebrow { font-size:13px; letter-spacing:.2em; font-weight:600; text-transform:uppercase; opacity:.95; }
    .vy-offer .amount { font-family:Fraunces, Georgia, serif; font-size:84px; line-height:1; margin:10px 0 4px; }
    .vy-offer .amount-sub { font-family:Fraunces, Georgia, serif; font-size:30px; }
    .vy-offer .meals { margin-top:10px; font-size:17px; opacity:.95; }
    .vy-offer .body { padding:36px 40px 12px; }
    .vy-offer h2 { font-family:Fraunces, Georgia, serif; font-weight:400; font-size:28px; margin:0 0 14px; }
    .vy-offer ul { list-style:none; margin:0; padding:0; }
    .vy-offer li { padding:13px 0 13px 22px; border-bottom:1px solid var(--line); position:relative; font-size:16px; color:#3f3a4b; }
    .vy-offer li:last-child { border-bottom:0; }
    .vy-offer li::before { content:''; position:absolute; left:2px; top:20px; width:8px; height:8px; border-radius:50%; background:var(--magenta); }
    .vy-offer .fine { padding:0 40px 36px; font-size:12px; color:var(--muted); text-align:center; line-height:1.55; }
    @media (max-width: 600px) {
        .vy-offer .top, .vy-offer .body { padding-left:16px; padding-right:16px; }
        .vy-offer .hero { padding:36px 16px; gap:20px; }
        .vy-offer .amount { font-size:60px; }
        .vy-offer .fine { padding:0 16px 28px; }
    }
</style>
@endonce

<div class="vy-offer">
    <div class="top">
        <div class="brand">
            <svg width="30" height="34" viewBox="0 0 30 34" aria-hidden="true">
                <defs><linearGradient id="vg-{{ $offer->key }}" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#FF3D8A"/><stop offset=".5" stop-color="#D63384"/><stop offset="1" stop-color="#7B2CBF"/></linearGradient></defs>
                <path d="M2 4h8l5 18 5-18h8L19 32h-8z" fill="url(#vg-{{ $offer->key }})"/>
                <circle cx="25" cy="5" r="4" fill="url(#vg-{{ $offer->key }})"/>
            </svg>
            Vaytoven
        </div>
        <div class="program">Managed Listing Program</div>
    </div>

    <div class="tag">Put your vacation property in tune with <b>Vaytoven</b>’s Listing Program</div>

    <div class="hero" style="background:{{ $offer->heroGradient() }};">
        @switch ($offer->icon)
            @case ('travel')
                <svg width="160" height="110" viewBox="0 0 160 110" fill="none" aria-hidden="true">
                    <path d="M20 52c20-10 40-16 64-20" stroke="rgba(255,255,255,.75)" stroke-width="2" stroke-dasharray="3 6" stroke-linecap="round"/>
                    <path d="M18 60c18-4 34-10 46-16" stroke="rgba(255,255,255,.6)" stroke-width="2" stroke-linecap="round"/>
                    <circle cx="112" cy="24" r="1.4" fill="#fff"/><circle cx="40" cy="30" r="1.2" fill="#fff"/><circle cx="60" cy="66" r="1.2" fill="#fff"/>
                    <ellipse cx="34" cy="86" rx="22" ry="8" fill="rgba(255,255,255,.35)"/><ellipse cx="50" cy="82" rx="16" ry="6" fill="rgba(255,255,255,.35)"/>
                    <ellipse cx="128" cy="96" rx="26" ry="9" fill="rgba(255,255,255,.35)"/><ellipse cx="146" cy="92" rx="18" ry="7" fill="rgba(255,255,255,.35)"/>
                </svg>
                @break
            @case ('cruise')
                <svg width="200" height="160" viewBox="0 0 200 160" fill="none" aria-hidden="true">
                    <ellipse cx="30" cy="18" rx="20" ry="6" fill="rgba(200,210,225,.6)"/>
                    <ellipse cx="160" cy="28" rx="24" ry="7" fill="rgba(200,210,225,.6)"/>
                    <circle cx="168" cy="58" r="11" fill="rgba(235,240,250,.95)"/>
                    <rect x="84" y="50" width="4" height="14" fill="#f3e8ff"/><rect x="92" y="50" width="4" height="14" fill="#f3e8ff"/>
                    <rect x="78" y="62" width="30" height="10" rx="2" fill="#e9a8d8"/>
                    <rect x="46" y="88" width="102" height="18" rx="2" fill="#e9a8d8"/>
                    @for ($i = 0; $i < 9; $i++) <rect x="{{ 53 + $i * 10 }}" y="94" width="5" height="5" fill="#fff"/> @endfor
                    @for ($i = 0; $i < 11; $i++) <rect x="{{ 44 + $i * 10 }}" y="112" width="6" height="6" fill="rgba(210,220,240,.9)"/> @endfor
                    <path d="M28 124h140l-10 20H38z" fill="#e9a8d8"/>
                    @for ($i = 0; $i < 11; $i++) <circle cx="{{ 50 + $i * 10 }}" cy="133" r="1.6" fill="#fff"/> @endfor
                    <path d="M10 148c20-4 40 4 60 0s40-4 60 0 40 4 60 0v6H10z" fill="rgba(170,190,215,.8)"/>
                    <rect x="8" y="152" width="184" height="6" rx="2" fill="rgba(150,170,200,.7)"/>
                </svg>
                @break
            @case ('hotel')
                <svg width="170" height="150" viewBox="0 0 170 150" fill="none" aria-hidden="true">
                    <path d="M28 140V62" stroke="rgba(255,255,255,.85)" stroke-width="3"/>
                    <path d="M28 62c-6-14-14-20-24-22M28 62c4-14 12-22 22-24M28 62c-2-12-6-22-12-28M28 62c6-10 16-14 26-14" stroke="rgba(255,255,255,.85)" stroke-width="2.5" stroke-linecap="round"/>
                    <circle cx="150" cy="30" r="14" fill="rgba(255,255,255,.9)"/>
                    @for ($r = 0; $r < 4; $r++) @for ($c = 0; $c < 6; $c++)
                        <rect x="{{ 68 + $c * 12 }}" y="{{ 78 + $r * 12 }}" width="8" height="8" fill="rgba(255,255,255,.55)"/>
                    @endfor @endfor
                    @for ($r = 0; $r < 3; $r++) @for ($c = 0; $c < 3; $c++)
                        <rect x="{{ 142 + $c * 10 }}" y="{{ 90 + $r * 11 }}" width="7" height="7" fill="rgba(255,255,255,.55)"/>
                    @endfor @endfor
                </svg>
                @break
            @default
                <svg width="140" height="130" viewBox="0 0 140 130" fill="none" stroke="#fff" stroke-width="2.5" aria-hidden="true">
                    <path d="M30 30h46c0 26-10 40-23 40S30 56 30 30z"/>
                    <path d="M53 70v42M36 114h34"/>
                    <path d="M62 26l30 12c-4 24-17 34-29 30"/>
                    <path d="M80 68l18 38M86 116l30-12"/>
                    <path d="M40 12l2 6 6 2-6 2-2 6-2-6-6-2 6-2z" fill="#fff" stroke="none"/>
                    <circle cx="12" cy="20" r="1.6" fill="#fff" stroke="none"/><circle cx="118" cy="36" r="1.6" fill="#fff" stroke="none"/><circle cx="8" cy="78" r="1.3" fill="#fff" stroke="none"/>
                </svg>
        @endswitch
        <div>
            <div class="eyebrow">{{ $offer->eyebrow }}</div>
            <div class="amount" @if (mb_strlen($offer->headline) > 12) style="font-size:clamp(44px, 7vw, 64px);line-height:1.05;" @endif>{{ $offer->headline }}</div>
            <div class="amount-sub">{{ $offer->subheadline }}</div>
            <div class="meals">{{ $offer->tagline }}</div>
        </div>
    </div>

    <div class="body">
        <h2>What’s included</h2>
        <ul>
            @foreach ($offer->included as $line)
                <li>{{ $line }}</li>
            @endforeach
        </ul>
    </div>

    @if (! empty($withButtons))
        <form method="POST" action="{{ route('member.incentive.acknowledge') }}" class="cta"
              style="margin:24px 40px 28px;border:2px solid var(--magenta);border-radius:16px;background:#fff5f9;padding:24px;text-align:center;">
            @csrf
            <p style="margin:0 0 16px;font-size:15px;color:#4a4458;">Thank you for enrolling in the Vaytoven Managed Listing Program. Your advertisement is waiting in your dashboard.</p>
            <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
                <button type="submit" name="choice" value="view"
                        style="border:2px solid var(--magenta);border-radius:10px;padding:14px 22px;font-weight:700;font-size:15px;letter-spacing:.04em;cursor:pointer;font-family:inherit;color:var(--magenta);background:#fff;">{{ $offer->viewButton() }}</button>
                <button type="submit" name="choice" value="continue"
                        style="border:0;border-radius:10px;padding:14px 22px;font-weight:700;font-size:15px;letter-spacing:.04em;cursor:pointer;font-family:inherit;color:#fff;background:linear-gradient(135deg,#FF3D8A 0%,#D63384 45%,#7B2CBF 100%);">{{ $offer->continueButton() }}</button>
            </div>
        </form>
    @endif

    <div class="fine">
        <strong>Certificate fulfilled by {{ $offer->provider }}</strong>{{ \Illuminate\Support\Str::after($offer->finePrint, 'Certificate fulfilled by '.$offer->provider) }}
    </div>
</div>
