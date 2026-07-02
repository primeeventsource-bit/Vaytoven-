<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Members · Managed Listing Program · Vaytoven</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Geist:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @include('partials.brand-styles')
    <style>
        .members-hero {
            background: linear-gradient(135deg, #fdf2f8 0%, #f5f3ff 100%);
            padding: 64px 24px clamp(48px, 6vw, 80px);
        }
        .members-hero-inner { max-width: 1100px; margin: 0 auto; display: grid; gap: 36px; grid-template-columns: 1fr; align-items: center; }
        @media (min-width: 880px) { .members-hero-inner { grid-template-columns: 1.3fr 1fr; } }
        .members-hero .eyebrow { font-size: 12px; letter-spacing:.12em; text-transform: uppercase; color: var(--magenta); font-weight: 600; }
        .members-hero h1 {
            font-family:'Fraunces',serif; font-size: clamp(34px, 5vw, 54px);
            font-weight: 600; letter-spacing: -.02em; line-height: 1.1; margin: 14px 0 18px;
        }
        .members-hero h1 em {
            font-style: italic;
            background: var(--gradient); -webkit-background-clip: text; background-clip: text; color: transparent;
        }
        .members-hero p { font-size: 17px; color: var(--ink); margin: 0 0 28px; max-width: 540px; line-height: 1.55; }
        .members-hero-cta { display: inline-flex; align-items: center; gap: 10px; padding: 14px 26px; border-radius: 999px;
            background: var(--gradient); color: #fff; font-weight: 600; font-size: 15px;
            box-shadow: 0 8px 32px -8px rgba(214,51,132,.4); transition: transform .12s; text-decoration: none;
        }
        .members-hero-cta:hover { transform: translateY(-1px); text-decoration: none; }
        .members-hero-card {
            background: #fff; border: 1px solid var(--line); border-radius: 18px;
            padding: 28px; box-shadow: 0 12px 32px -16px rgba(123,44,191,.18);
        }
        .members-hero-card h4 { font-size: 12px; letter-spacing:.1em; text-transform: uppercase; color: var(--muted); font-weight: 600; margin: 0 0 18px; }
        .members-hero-card-row { display: flex; justify-content: space-between; padding: 12px 0; border-top: 1px solid var(--line); font-size: 14px; }
        .members-hero-card-row:first-of-type { border-top: 0; }
        .members-hero-card-row .num { font-family:'Fraunces',serif; font-weight:600; }
        .members-hero-card-total {
            display: flex; justify-content: space-between; padding: 18px 0 0;
            margin-top: 10px; border-top: 2px solid var(--ink); font-weight: 600;
        }
        .members-hero-card-total .num {
            font-family:'Fraunces',serif; font-size: 28px;
            background: var(--gradient); -webkit-background-clip: text; background-clip: text; color: transparent;
        }
        .members-hero-card .disclaimer { font-size: 12px; color: var(--muted); margin: 14px 0 0; line-height: 1.5; }

        .members-shell { max-width: 1100px; margin: 0 auto; padding: 60px 24px; }
        .members-section { margin-bottom: 72px; }
        .members-section-eyebrow { font-size: 12px; letter-spacing:.12em; text-transform: uppercase; color: var(--muted); font-weight: 600; }
        .members-section h2 {
            font-family:'Fraunces',serif; font-size: clamp(28px, 3.5vw, 40px);
            font-weight: 600; letter-spacing: -.02em; margin: 8px 0 18px;
        }
        .members-section .lede { color: var(--muted); font-size: 16px; max-width: 620px; line-height: 1.6; }

        .members-clubs {
            display: grid; gap: 14px; grid-template-columns: 1fr 1fr;
            margin-top: 32px;
        }
        @media (min-width: 700px) { .members-clubs { grid-template-columns: 1fr 1fr 1fr; } }
        @media (min-width: 980px) { .members-clubs { grid-template-columns: 1fr 1fr 1fr 1fr; } }
        .members-club {
            background: #fff; border: 1px solid var(--line); border-radius: 12px;
            padding: 18px; text-align: center;
            font-family:'Fraunces',serif; font-weight: 600; font-size: 16px;
        }
        .members-club .tier { display:block; font-family:'Geist',sans-serif; font-size: 12px; color: var(--muted); font-weight: 500; margin-top: 6px; letter-spacing: .04em; }

        .members-flow { counter-reset: m-step; }
        .members-flow-step { display: grid; grid-template-columns: 60px 1fr; gap: 22px; padding: 28px 0; border-top: 1px solid var(--line); counter-increment: m-step; }
        .members-flow-step:first-of-type { border-top: 0; }
        .members-flow-step-number::before {
            content: counter(m-step, decimal-leading-zero);
            font-family:'Fraunces',serif; font-size: 32px; font-weight: 600;
            background: var(--gradient); -webkit-background-clip: text; background-clip: text; color: transparent;
        }
        .members-flow-step h3 { font-family:'Fraunces',serif; font-size: 20px; font-weight: 600; margin: 0 0 8px; }
        .members-flow-step p { color: var(--muted); font-size: 15px; line-height: 1.65; margin: 0; }

        .members-faq { background: #fff; border: 1px solid var(--line); border-radius: 14px; }
        .members-faq details { padding: 18px 22px; border-top: 1px solid var(--line); }
        .members-faq details:first-of-type { border-top: 0; }
        .members-faq summary { font-family:'Fraunces',serif; font-weight: 600; font-size: 16px; cursor: pointer; outline: none; list-style: none; display: flex; justify-content: space-between; align-items: center; }
        .members-faq summary::-webkit-details-marker { display: none; }
        .members-faq summary::after { content: '+'; color: var(--purple); font-size: 22px; font-weight: 400; transition: transform .15s; }
        .members-faq details[open] summary::after { transform: rotate(45deg); }
        .members-faq details p { color: var(--ink); font-size: 14.5px; line-height: 1.6; margin: 14px 0 0; }

        .members-final-cta {
            background: linear-gradient(135deg, #1d1f21 0%, #3a1d4d 100%);
            color: #fff; border-radius: 20px; padding: clamp(36px, 5vw, 56px); text-align: center;
        }
        .members-final-cta h2 { font-family:'Fraunces',serif; font-size: clamp(26px, 3.5vw, 36px); font-weight: 600; letter-spacing: -.02em; margin: 0 0 14px; color: #fff; }
        .members-final-cta p { color: #d6d3d1; max-width: 540px; margin: 0 auto 24px; font-size: 16px; }
    </style>
</head>
<body>
    @include('partials.top-nav', ['current' => 'members'])

    <header class="members-hero">
        <div class="members-hero-inner">
            <div>
                <div class="eyebrow">Managed Listing Program</div>
                <h1>Turn unused points into <em>real income.</em></h1>
                <p>If you own points-based vacation property — Marriott, Hilton, Disney, Wyndham, RCI, Interval, or any major club — Vaytoven's Managed Listing Program rents the weeks you don't use, with zero hassle and full compliance with your program rules.</p>
                <a href="/#members" class="members-hero-cta" data-track-audience="member" data-track-cta="members_page_apply">
                    Get on the program
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
                </a>
            </div>
            <div class="members-hero-card">
                <h4>Sample 3-week rental — illustrative</h4>
                <div class="members-hero-card-row">
                    <span style="color:var(--muted);">Maui beachfront, peak</span>
                    <span class="num">$5,280</span>
                </div>
                <div class="members-hero-card-row">
                    <span style="color:var(--muted);">Orlando studio, school break</span>
                    <span class="num">$1,920</span>
                </div>
                <div class="members-hero-card-row">
                    <span style="color:var(--muted);">Cabo villa, shoulder</span>
                    <span class="num">$3,240</span>
                </div>
                <div class="members-hero-card-total">
                    <span>Net to member</span>
                    <span class="num">$10,440</span>
                </div>
                <p class="disclaimer">Net of upfront weekly cost ($200–$800 + tax) + program subscription fee. Quoted in writing on the onboarding call before commitment. Illustrative only — actual payouts vary by property, season, and inventory.</p>
            </div>
        </div>
    </header>

    <main class="members-shell">

        <section class="members-section">
            <div class="members-section-eyebrow">Eligible programs</div>
            <h2>We work with most major points-based clubs.</h2>
            <p class="lede">Points across multiple clubs? Mention them all on the onboarding call — we manage them as one portfolio.</p>

            <div class="members-clubs">
                <div class="members-club">Marriott Vacation Club <span class="tier">Premier &amp; Destinations</span></div>
                <div class="members-club">Hilton Grand Vacations <span class="tier">All tiers</span></div>
                <div class="members-club">Disney Vacation Club <span class="tier">Member-direct only</span></div>
                <div class="members-club">Wyndham Destinations <span class="tier">Including CLUB</span></div>
                <div class="members-club">RCI Points <span class="tier">Points + Weeks</span></div>
                <div class="members-club">Interval International <span class="tier">Standard &amp; Platinum</span></div>
                <div class="members-club">Worldmark <span class="tier">By RCI</span></div>
                <div class="members-club">Other major clubs <span class="tier">Reviewed case by case</span></div>
            </div>
            <p class="lede" style="margin-top: 22px;">Fixed-week and right-to-use systems are reviewed case by case. Some programs explicitly forbid managed rental — we'll tell you up front rather than risk your membership. Compliance with your club's rules is non-negotiable on our side.</p>
        </section>

        <section class="members-section">
            <div class="members-section-eyebrow">Benefits</div>
            <h2>What you get when you list with us.</h2>

            <ul style="margin-top: 28px; padding-left: 22px; font-size: 15.5px; line-height: 2; color: var(--ink);">
                <li><strong>Listed and booked across our network</strong> and partner channels under Vaytoven's escrow account, keeping your booking compliant with your club's rental policy.</li>
                <li><strong>Pricing transparency.</strong> Upfront weekly program cost ($200–$800 + tax, varying by property tier and season) plus a flat program subscription fee — both quoted in writing on the onboarding call before any commitment.</li>
                <li><strong>Rate-locked for the term.</strong> Whatever we quote is what applies; we won't raise it mid-term without your written consent.</li>
                <li><strong>No separate percentage cut.</strong> Once those costs are covered on each booking, the rest is yours.</li>
                <li><strong>You stay in control.</strong> Keep, gift, or rent whichever weeks you want each year. Withdrawal of a week from the program doesn't affect bookings already confirmed against it.</li>
                <li><strong>Real human onboarding.</strong> A specialist contacts you within one business day of your enquiry. No bots until your portfolio is live.</li>
                <li><strong>Same payout schedule as standard hosts.</strong> Funds release 24 hours after guest check-in by direct bank deposit (ACH).</li>
            </ul>
        </section>

        <section class="members-section">
            <div class="members-section-eyebrow">How it works</div>
            <h2>From enquiry to net payout.</h2>

            <div class="members-flow" style="margin-top: 32px;">
                <div class="members-flow-step">
                    <div class="members-flow-step-number"></div>
                    <div>
                        <h3>You submit an enquiry</h3>
                        <p>Drop your name, contact details, club, property, and points balance via the form on our home page. Takes about 90 seconds. You get an automated confirmation email with a reference code (format <code style="font-family:'SFMono-Regular',Consolas,monospace; font-size: 12.5px; background: #f5f3ff; padding: 2px 6px; border-radius: 4px;">VYT-XXXXXXXX</code>) you can quote in any reply.</p>
                    </div>
                </div>
                <div class="members-flow-step">
                    <div class="members-flow-step-number"></div>
                    <div>
                        <h3>Specialist call within one business day</h3>
                        <p>A real person on the member team reaches out. We confirm your program rules, audit which weeks are eligible to rent, and quote the exact upfront weekly cost + subscription fee for your specific portfolio. You decide whether to move forward — no commitment until you sign.</p>
                    </div>
                </div>
                <div class="members-flow-step">
                    <div class="members-flow-step-number"></div>
                    <div>
                        <h3>Onboarding under our escrow account</h3>
                        <p>Eligible weeks are loaded into Vaytoven's escrow account so guest payment, custodial holding, and payout flow to you all happen on Vaytoven infrastructure. This structure keeps the booking compliant with your club's rental policy by interposing Vaytoven as the booking party.</p>
                    </div>
                </div>
                <div class="members-flow-step">
                    <div class="members-flow-step-number"></div>
                    <div>
                        <h3>Listings live + first booking</h3>
                        <p>We list the weeks across our network and partner channels. As guests check in, your net payouts release 24 hours later, on the same schedule as standard hosts. You see scheduled payouts in your member dashboard.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="members-section">
            <div class="members-section-eyebrow">Frequently asked</div>
            <h2>Member program FAQs.</h2>

            <div class="members-faq" style="margin-top: 32px;">
                <details>
                    <summary>Will Vaytoven booking my weeks violate my club's rules?</summary>
                    <p>No. We list under Vaytoven's escrow account, not under your member account. The booking party on the contract is Vaytoven, which keeps the transaction compliant with your club's rental policy in every program we currently support. If your club explicitly forbids managed rental we'll tell you on the onboarding call before any commitment.</p>
                </details>
                <details>
                    <summary>What does the upfront weekly cost cover?</summary>
                    <p>Listing operations: pricing, calendar management, guest support, payment processing fees, our 24/7 trust &amp; safety coverage, and the platform infrastructure your booking runs on. The exact figure ($200–$800 per week + applicable tax) varies by property tier and season; we quote your specific portfolio in writing before commitment.</p>
                </details>
                <details>
                    <summary>What's the program subscription fee?</summary>
                    <p>A flat fee that covers your member dashboard, ongoing portfolio management, and recurring compliance review across your club rules. Quoted alongside the upfront weekly cost on the onboarding call.</p>
                </details>
                <details>
                    <summary>Can fees go up after I sign?</summary>
                    <p>No, not without your written consent. The Member Agreement explicitly commits us to the quoted figures for the term. If we propose changes at renewal, you get plenty of notice and can decline.</p>
                </details>
                <details>
                    <summary>What happens if I want to use a week myself after I've enrolled it?</summary>
                    <p>You stay in control. Withdraw the week from the program before it's confirmed to a guest and it's yours. Bookings that are already confirmed are honoured to their conclusion — you can't cancel a confirmed guest just to use the week yourself.</p>
                </details>
                <details>
                    <summary>How do I get paid?</summary>
                    <p>Direct bank deposit (ACH), 24 hours after each guest checks in. Same schedule as standard hosts. First payouts require one-time payout enrollment — our payments team verifies your identity and banking details, usually within a business day.</p>
                </details>
                <details>
                    <summary>What if my club isn't on the list?</summary>
                    <p>Mention it on your enquiry — we review case by case. New programs get added when we're confident we can structure compliant rentals for them.</p>
                </details>
                <details>
                    <summary>How long is the agreement?</summary>
                    <p>Standard term is one year, with 30 days' written notice for either side to terminate at renewal. Bookings already confirmed at termination are honoured; payouts continue per the original schedule.</p>
                </details>
            </div>
        </section>

        <section class="members-section">
            <div class="members-final-cta">
                <h2>See what your portfolio could earn.</h2>
                <p>Specialist call within one business day. No commitment until you've reviewed the written quote.</p>
                <a href="/#members" class="members-hero-cta" data-track-audience="member" data-track-cta="members_page_apply_footer">
                    Request a call →
                </a>
            </div>
        </section>
    </main>

    <script src="/vyt-track.js" defer></script>
</body>
</html>
