{{-- Styles for partials/package-cards. Shared so the homepage and the
     activation page render the tiers identically. --}}
<style>
        /* Fraunces is a variable font with an optical-size axis. On auto the
           browser picks a more decorative cut as type gets larger — flared
           serifs and exaggerated curves — which is what reads as wavy on
           headings. Pinned here; the property inherits, so this one
           declaration reaches every heading below it. */
        html { font-optical-sizing: none; }

    .pkg-grid { display:grid; gap:20px; grid-template-columns:1fr; max-width:1100px; margin:0 auto; }
    @media (min-width:900px) { .pkg-grid { grid-template-columns:repeat(3,1fr); align-items:start; } }

    .pkg-card {
        position:relative; display:flex; flex-direction:column;
        background:#fff; border:2px solid var(--line); border-radius:20px;
        padding:30px 26px 26px; text-align:center; color:var(--ink);
        transition:transform .18s cubic-bezier(.2,.7,.3,1), border-color .18s, box-shadow .18s;
    }
    .pkg-card.has-badge { margin-top:14px; }
    a.pkg-card:hover {
        transform:translateY(-5px); border-color:var(--magenta);
        box-shadow:0 20px 48px -22px rgba(214,51,132,.5); text-decoration:none;
    }
    .pkg-card.is-silver { border-color:#cbd5e1; }
    .pkg-card.is-gold   { border-color:var(--magenta); }
    @media (min-width:900px) { .pkg-card.is-gold { transform:scale(1.03); } }
    @media (min-width:900px) { a.pkg-card.is-gold:hover { transform:scale(1.03) translateY(-5px); } }

    .pkg-badge {
        position:absolute; top:-14px; left:50%; transform:translateX(-50%);
        background:var(--gradient); color:#fff; font-size:11px; font-weight:700;
        letter-spacing:.1em; padding:6px 16px; border-radius:999px; white-space:nowrap;
        box-shadow:0 6px 18px -8px rgba(214,51,132,.7);
    }

    .pkg-emoji { font-size:34px; line-height:1; }
    .pkg-name {
        font-size:13px; font-weight:800; letter-spacing:.2em;
        margin-top:10px; color:var(--ink);
    }
    .pkg-headline {
        font-size:11.5px; font-weight:600; letter-spacing:.12em;
        color:var(--muted); margin-top:4px;
    }
    .pkg-price {
        font-family:'Fraunces',serif; font-size:46px; font-weight:600;
        letter-spacing:-.03em; margin:14px 0 2px;
    }
    .pkg-price span { font-family:'Geist',sans-serif; font-size:15px; font-weight:500; color:var(--muted); }
    .pkg-allowance {
        font-size:11.5px; font-weight:700; letter-spacing:.1em; color:var(--magenta);
    }
    .pkg-tagline {
        font-family:'Fraunces',serif; font-size:16px; font-weight:600;
        line-height:1.35; margin:16px 0 0; text-wrap:balance;
    }
    .pkg-desc {
        font-size:13.5px; line-height:1.6; color:var(--muted);
        margin:12px 0 0; text-align:left;
    }

    .pkg-features {
        list-style:none; padding:0; margin:20px 0 24px; text-align:left;
        display:grid; gap:9px; font-size:13.5px;
    }
    .pkg-features li { display:grid; grid-template-columns:20px 1fr; gap:8px; align-items:start; }
    .pkg-features li.is-excluded { color:rgba(26,20,38,.38); }
    .pkg-features strong { font-weight:600; }
    .pkg-tick { color:var(--magenta); font-weight:700; }
    .pkg-features li.is-excluded .pkg-tick { color:rgba(26,20,38,.3); }

    /* The CTA carries the bottom spacing now that the bonus-benefit panel
       between the feature list and the button is gone. */
    .pkg-cta {
        display:block; margin-top:auto; padding:13px 20px; border-radius:999px;
        background:var(--gradient); color:#fff; font-weight:700; font-size:13px;
        letter-spacing:.08em;
    }
    .pkg-card.is-bronze .pkg-cta { background:var(--ink); }

    /* Comparison table */
    .pkg-compare-wrap { overflow-x:auto; max-width:1100px; margin:0 auto; }
    .pkg-compare {
        width:100%; min-width:640px; border-collapse:collapse;
        background:#fff; border:1px solid var(--line); border-radius:16px; overflow:hidden;
    }
    .pkg-compare th, .pkg-compare td {
        padding:14px 16px; text-align:center; font-size:13.5px;
        border-top:1px solid var(--line);
    }
    .pkg-compare thead th { border-top:0; background:var(--paper-2); font-size:12px; letter-spacing:.1em; }
    .pkg-compare tbody th {
        text-align:left; font-weight:500; color:var(--muted); white-space:nowrap;
    }
    .pkg-compare .is-excluded { color:rgba(26,20,38,.35); }
    .pkg-compare .col-gold { background:rgba(214,51,132,.05); }
</style>
