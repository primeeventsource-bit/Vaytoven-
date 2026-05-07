<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Become a Host · Vaytoven</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Geist:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @include('partials.brand-styles')
    <style>
        .host-hero {
            background: linear-gradient(135deg, #1d1f21 0%, #3a1d4d 100%);
            color: #fff; padding: 64px 24px clamp(48px, 6vw, 80px);
            text-align: center;
        }
        .host-hero-inner { max-width: 800px; margin: 0 auto; }
        .host-hero .eyebrow {
            font-size: 12px; letter-spacing:.12em; text-transform: uppercase;
            color: #ff9bc3; font-weight: 600;
        }
        .host-hero h1 {
            font-family:'Fraunces',serif; font-size: clamp(36px, 6vw, 60px);
            font-weight: 600; letter-spacing: -.02em; margin: 14px 0 18px;
            line-height: 1.1;
        }
        .host-hero h1 em {
            font-style: italic;
            background: linear-gradient(135deg,#FF3D8A,#7B2CBF);
            -webkit-background-clip: text; background-clip: text; color: transparent;
        }
        .host-hero p { font-size: 17px; color: #d6d3d1; margin: 0 auto 28px; max-width: 580px; line-height: 1.55; }
        .host-hero .cta-row { display: inline-flex; gap: 12px; flex-wrap: wrap; justify-content: center; }
        .host-hero .cta-primary {
            background: var(--gradient); color: #fff; padding: 14px 26px;
            border-radius: 999px; font-weight: 600; font-size: 15px;
            text-decoration: none; display: inline-flex; align-items: center; gap: 10px;
            box-shadow: 0 8px 32px -8px rgba(255,61,138,.5);
            transition: transform .12s;
        }
        .host-hero .cta-primary:hover { transform: translateY(-1px); text-decoration: none; }
        .host-hero .cta-secondary {
            color: #fff; padding: 14px 22px; border-radius: 999px;
            font-weight: 500; font-size: 14px; text-decoration: none;
            border: 1px solid rgba(255,255,255,.25);
        }
        .host-hero .cta-secondary:hover { background: rgba(255,255,255,.06); text-decoration: none; }

        .host-shell { max-width: 1100px; margin: 0 auto; padding: 60px 24px; }
        .host-section { margin-bottom: 72px; }
        .host-section-eyebrow { font-size: 12px; letter-spacing:.12em; text-transform: uppercase; color: var(--muted); font-weight: 600; }
        .host-section h2 {
            font-family:'Fraunces',serif; font-size: clamp(28px, 3.5vw, 40px);
            font-weight: 600; letter-spacing: -.02em; margin: 8px 0 18px;
        }
        .host-section .lede { color: var(--muted); font-size: 16px; max-width: 620px; line-height: 1.6; }

        .host-benefits {
            display: grid; gap: 18px; grid-template-columns: 1fr;
            margin-top: 32px;
        }
        @media (min-width: 700px) { .host-benefits { grid-template-columns: 1fr 1fr; } }
        @media (min-width: 980px) { .host-benefits { grid-template-columns: 1fr 1fr 1fr; } }
        .host-benefit {
            background: #fff; border: 1px solid var(--line); border-radius: 14px;
            padding: 22px;
        }
        .host-benefit-icon {
            display: inline-flex; align-items: center; justify-content: center;
            width: 38px; height: 38px; border-radius: 10px;
            background: #f5f3ff; color: var(--purple);
            font-family:'Fraunces',serif; font-size: 18px; font-weight: 600;
            margin-bottom: 14px;
        }
        .host-benefit h3 { font-family:'Fraunces',serif; font-size: 18px; font-weight: 600; margin: 0 0 8px; }
        .host-benefit p { color: var(--muted); font-size: 14px; line-height: 1.6; margin: 0; }

        .host-steps { counter-reset: step; }
        .host-step {
            display: grid; grid-template-columns: 60px 1fr; gap: 22px;
            padding: 28px 0; border-top: 1px solid var(--line);
            counter-increment: step;
        }
        .host-step:first-of-type { border-top: 0; }
        .host-step-number::before {
            content: counter(step, decimal-leading-zero);
            font-family:'Fraunces',serif; font-size: 32px; font-weight: 600;
            background: var(--gradient); -webkit-background-clip: text;
            background-clip: text; color: transparent;
        }
        .host-step h3 { font-family:'Fraunces',serif; font-size: 20px; font-weight: 600; margin: 0 0 8px; }
        .host-step p { color: var(--muted); font-size: 15px; line-height: 1.65; margin: 0; }

        .host-stats {
            background: linear-gradient(135deg, #f5f3ff 0%, #fdf2f8 100%);
            border-radius: 18px; padding: 40px clamp(20px, 4vw, 48px);
            display: grid; gap: 28px; grid-template-columns: 1fr;
            text-align: center;
        }
        @media (min-width: 700px) { .host-stats { grid-template-columns: 1fr 1fr 1fr; } }
        .host-stat strong {
            display: block; font-family:'Fraunces',serif;
            font-size: clamp(28px, 4vw, 42px); font-weight: 600; line-height: 1;
            background: var(--gradient); -webkit-background-clip: text;
            background-clip: text; color: transparent;
        }
        .host-stat span {
            display: block; font-size: 13px; color: var(--muted);
            margin-top: 8px; letter-spacing: .04em;
        }

        .host-final-cta {
            background: #1d1f21; color: #fff; border-radius: 20px;
            padding: clamp(36px, 5vw, 56px); text-align: center;
        }
        .host-final-cta h2 {
            font-family:'Fraunces',serif; font-size: clamp(26px, 3.5vw, 36px);
            font-weight: 600; letter-spacing: -.02em; margin: 0 0 14px; color: #fff;
        }
        .host-final-cta p { color: #d6d3d1; max-width: 540px; margin: 0 auto 24px; font-size: 16px; }
    </style>
</head>
<body>
    @include('partials.top-nav', ['current' => 'become-a-host'])

    <header class="host-hero">
        <div class="host-hero-inner">
            <div class="eyebrow">For property owners</div>
            <h1>Your second home, <em>earning its keep.</em></h1>
            <p>Half the take-home of a hotel. Twice the booking pace of generic listing sites. List once, we handle the rest — photography, copywriting, pricing, payouts.</p>
            <div class="cta-row">
                <a href="{{ route('host.onboarding.index') }}" class="cta-primary" data-track-audience="host" data-track-cta="become_host_apply">
                    Start your application
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
                </a>
                <a href="#how-it-works" class="cta-secondary" data-track-audience="host" data-track-cta="become_host_learn">
                    See how it works
                </a>
            </div>
        </div>
    </header>

    <main class="host-shell">

        <section class="host-section">
            <div class="host-stats">
                <div class="host-stat">
                    <strong>3%</strong>
                    <span>HOST FEE — NOT 15, NOT 20</span>
                </div>
                <div class="host-stat">
                    <strong>24h</strong>
                    <span>PAYOUT AFTER GUEST CHECK-IN</span>
                </div>
                <div class="host-stat">
                    <strong>$250K</strong>
                    <span>DAMAGE COVER PER STAY, INCLUDED</span>
                </div>
            </div>
        </section>

        <section class="host-section">
            <div class="host-section-eyebrow">Why Vaytoven</div>
            <h2>Hosting that respects your time and your margin.</h2>
            <p class="lede">We picked four things hosts complain loudest about on other platforms — and fixed each one.</p>

            <div class="host-benefits">
                <div class="host-benefit">
                    <div class="host-benefit-icon">¢</div>
                    <h3>3% host fee, flat.</h3>
                    <p>Not 15. Not 20. Three. We make our money on volume and operational leverage, not by skimming hosts.</p>
                </div>
                <div class="host-benefit">
                    <div class="host-benefit-icon">⏱</div>
                    <h3>Payouts in 24 hours.</h3>
                    <p>Funds release the day after guest check-in via Stripe Connect. No 30-day holding pattern. No "pending" line items.</p>
                </div>
                <div class="host-benefit">
                    <div class="host-benefit-icon">🛡</div>
                    <h3>$250K damage cover, baked in.</h3>
                    <p>Up to $250,000 per stay, no separate purchase, no per-booking surcharge. First claim each year has zero deductible.</p>
                </div>
                <div class="host-benefit">
                    <div class="host-benefit-icon">📷</div>
                    <h3>Listing concierge.</h3>
                    <p>Photography, copy, pricing — handled by our team in supported metros at no charge for your first listing.</p>
                </div>
                <div class="host-benefit">
                    <div class="host-benefit-icon">🤝</div>
                    <h3>One contract, one dashboard.</h3>
                    <p>Sign once via DocuSign. Track upcoming bookings, payouts, and reviews from a single host dashboard.</p>
                </div>
                <div class="host-benefit">
                    <div class="host-benefit-icon">⚖</div>
                    <h3>Compliance built in.</h3>
                    <p>We surface your local short-term-rental rules during onboarding. Your job is hosting; ours is staying within the lines.</p>
                </div>
            </div>
        </section>

        <section class="host-section" id="how-it-works">
            <div class="host-section-eyebrow">Onboarding</div>
            <h2>From application to your first booking in 7–14 days.</h2>
            <p class="lede">Everything happens online. Most hosts complete identity verification in under 10 minutes.</p>

            <div class="host-steps" style="margin-top: 32px;">
                <div class="host-step">
                    <div class="host-step-number"></div>
                    <div>
                        <h3>Apply</h3>
                        <p>Tell us about your property — location, type, capacity, photos if you have them. We screen for fit before continuing; if your area or property type isn't supported yet we'll tell you up front.</p>
                    </div>
                </div>
                <div class="host-step">
                    <div class="host-step-number"></div>
                    <div>
                        <h3>Verify identity (Stripe-hosted KYC)</h3>
                        <p>Stripe Connect Express runs the KYC flow on their secure pages — legal name, DOB, business type, bank account, photo ID. Vaytoven never sees your bank credentials or ID document. Most accounts clear in minutes.</p>
                    </div>
                </div>
                <div class="host-step">
                    <div class="host-step-number"></div>
                    <div>
                        <h3>Concierge buildout</h3>
                        <p>Our team arranges photography, writes the listing copy, prices the property based on local comps, and surfaces the local short-term-rental rules. You review and approve before going live.</p>
                    </div>
                </div>
                <div class="host-step">
                    <div class="host-step-number"></div>
                    <div>
                        <h3>Go live + first booking</h3>
                        <p>Listings activate the moment you approve. Most hosts see their first booking within 14 days; payouts release 24 hours after each guest check-in.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="host-section">
            <div class="host-section-eyebrow">Requirements</div>
            <h2>What we ask of every listing.</h2>
            <ul style="font-size: 15px; line-height: 1.85; padding-left: 22px; color: var(--ink);">
                <li>Working smoke detectors and CO detectors on every level.</li>
                <li>Fire extinguisher in or near the kitchen.</li>
                <li>Basic first-aid kit.</li>
                <li>Daylight, in-focus listing photos that represent the actual unit.</li>
                <li>Local short-term-rental permit if your jurisdiction requires one — we surface the rule during onboarding but the permit application itself is on you.</li>
                <li>Annual self-attestation that the safety items above are still in place.</li>
            </ul>
        </section>

        <section class="host-section">
            <div class="host-final-cta">
                <h2>Ready to list?</h2>
                <p>The application takes about five minutes. KYC takes another five. Most hosts go live within two weeks.</p>
                <a href="{{ route('host.onboarding.index') }}" class="cta-primary" style="background: var(--gradient);" data-track-audience="host" data-track-cta="become_host_apply_footer">
                    Start your application →
                </a>
            </div>
        </section>
    </main>

    <script src="/vyt-track.js" defer></script>
</body>
</html>
