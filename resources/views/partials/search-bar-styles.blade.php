<style>
        /* Fraunces is a variable font with an optical-size axis. On auto the
           browser picks a more decorative cut as type gets larger — flared
           serifs and exaggerated curves — which is what reads as wavy on
           headings. Pinned here; the property inherits, so this one
           declaration reaches every heading below it. */
        html { font-optical-sizing: none; }

    /* ── Vrbo-style search bar ────────────────────────────────────────────── */
    .vyt-search {
        background: #fff; border-radius: 18px;
        box-shadow: 0 12px 40px -12px rgba(0,0,0,.18), 0 1px 3px rgba(0,0,0,.04);
        display: grid; gap: 0;
        grid-template-columns: 2fr 1.2fr 1.2fr 96px;
        position: relative; max-width: 920px; margin: 0 auto;
    }
    .vyt-search.is-compact {
        max-width: 760px;
        box-shadow: 0 4px 14px -8px rgba(123,44,191,.14), 0 1px 2px rgba(0,0,0,.04);
    }
    .vyt-search-field {
        position: relative; display: flex; flex-direction: column;
        padding: 14px 20px; cursor: pointer;
        background: transparent; border: 0; border-right: 1px solid var(--line);
        text-align: left; min-width: 0;
        transition: background .12s ease;
    }
    .vyt-search-field:hover { background: #faf7ff; border-radius: 18px; }
    .vyt-search-field:last-of-type { border-right: 0; }
    .vyt-search-field:first-child { border-top-left-radius: 18px; border-bottom-left-radius: 18px; }
    .vyt-search-label {
        font-size: 11px; letter-spacing: .08em; text-transform: uppercase;
        color: var(--muted); font-weight: 600; margin-bottom: 4px;
        font-family: 'Geist', sans-serif;
    }
    .vyt-search-field input,
    .vyt-search-value {
        font-size: 15px; font-weight: 500; color: var(--ink);
        background: transparent; border: 0; outline: 0; padding: 0;
        font-family: 'Geist', sans-serif; line-height: 1.3;
        width: 100%; text-align: left;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .vyt-search-field input::placeholder { color: var(--muted); }
    .vyt-search-trigger { font-family: inherit; }
    .vyt-search-trigger .vyt-search-value:not(:empty) { color: var(--ink); }
    .vyt-search-submit {
        margin: 8px; display: flex; align-items: center; gap: 8px;
        justify-content: center;
        background: var(--gradient); color: #fff; border: 0;
        border-radius: 14px; cursor: pointer; font-weight: 600;
        font-size: 14px; padding: 0 18px;
        font-family: 'Geist', sans-serif;
        transition: transform .12s ease, box-shadow .15s ease;
        box-shadow: 0 6px 16px -8px rgba(214,51,132,.6);
    }
    .vyt-search-submit:hover { transform: translateY(-1px); }
    .vyt-search-submit svg { width: 16px; height: 16px; }
    .vyt-search.is-compact .vyt-search-submit { font-size: 13px; padding: 0 14px; }

    /* Mobile: stack vertically */
    @media (max-width: 720px) {
        .vyt-search { grid-template-columns: 1fr; max-width: 100%; }
        .vyt-search-field { border-right: 0; border-bottom: 1px solid var(--line); padding: 12px 18px; }
        .vyt-search-field:last-of-type { border-bottom: 0; }
        .vyt-search-field:first-child { border-radius: 18px 18px 0 0; }
        .vyt-search-submit { margin: 12px; padding: 14px; }
    }

    /* ── Suggestion dropdown ──────────────────────────────────────────────── */
    .vyt-search-suggest {
        position: absolute; top: 100%; left: 0; right: 0;
        margin-top: 6px;
        background: #fff; border: 1px solid var(--line); border-radius: 14px;
        box-shadow: 0 12px 40px -12px rgba(0,0,0,.18);
        max-height: 360px; overflow-y: auto;
        z-index: 50;
    }
    .vyt-suggest-section {
        padding: 8px 0; border-top: 1px solid var(--line);
    }
    .vyt-suggest-section:first-of-type { border-top: 0; }
    .vyt-suggest-heading {
        font-size: 11px; letter-spacing: .08em; text-transform: uppercase;
        color: var(--muted); font-weight: 600; padding: 6px 18px;
    }
    .vyt-suggest-item {
        display: grid; grid-template-columns: 36px 1fr; gap: 12px;
        align-items: center; padding: 10px 18px;
        cursor: pointer; transition: background .1s;
    }
    .vyt-suggest-item:hover, .vyt-suggest-item.is-highlighted { background: #faf7ff; }
    .vyt-suggest-icon {
        display: flex; align-items: center; justify-content: center;
        width: 36px; height: 36px; border-radius: 10px;
        background: #f5f3ff; color: var(--purple);
        font-size: 16px;
    }
    .vyt-suggest-text strong { display: block; font-size: 14px; font-weight: 600; }
    .vyt-suggest-text span { display: block; font-size: 12px; color: var(--muted); margin-top: 2px; }
    .vyt-suggest-empty {
        padding: 24px 18px; text-align: center; color: var(--muted); font-size: 13px;
    }

    /* ── Popovers (calendar + guests) ──────────────────────────────────────── */
    .vyt-search-popover {
        position: absolute; top: 100%; margin-top: 6px;
        background: #fff; border: 1px solid var(--line); border-radius: 16px;
        box-shadow: 0 16px 48px -12px rgba(0,0,0,.2);
        z-index: 50; padding: 18px;
    }
    .vyt-search-popover[data-vyt-popover="dates"] {
        left: 50%; transform: translateX(-50%); width: min(720px, 95vw);
    }
    .vyt-search-popover[data-vyt-popover="guests"] {
        right: 96px; width: min(360px, 92vw);
    }
    .vyt-search-popover-actions {
        display: flex; justify-content: space-between; align-items: center;
        margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--line);
    }
    .vyt-popover-clear {
        background: transparent; border: 0; color: var(--muted);
        font-family: inherit; font-size: 14px; cursor: pointer;
        text-decoration: underline;
    }
    .vyt-popover-done {
        background: var(--ink); color: #fff; border: 0; border-radius: 999px;
        padding: 10px 22px; font-family: inherit; font-size: 14px;
        font-weight: 600; cursor: pointer;
    }
    @media (max-width: 720px) {
        .vyt-search-popover { left: 8px !important; right: 8px !important; transform: none !important; width: auto; }
    }

    /* ── Calendar ──────────────────────────────────────────────────────────── */
    .vyt-cal {
        display: grid; gap: 24px; grid-template-columns: 1fr 1fr;
    }
    @media (max-width: 720px) { .vyt-cal { grid-template-columns: 1fr; } }
    .vyt-cal-month h4 {
        text-align: center; font-family: 'Fraunces', serif; font-weight: 600;
        font-size: 16px; margin: 0 0 12px;
    }
    .vyt-cal-grid {
        display: grid; grid-template-columns: repeat(7, 1fr);
        gap: 2px; font-size: 13px;
    }
    .vyt-cal-grid .dow {
        text-align: center; color: var(--muted); font-size: 11px;
        text-transform: uppercase; letter-spacing: .08em; padding: 6px 0;
    }
    .vyt-cal-day {
        aspect-ratio: 1; display: flex; align-items: center; justify-content: center;
        border-radius: 50%; cursor: pointer; user-select: none;
        font-weight: 500;
        transition: background .1s, color .1s;
    }
    .vyt-cal-day.is-empty { cursor: default; }
    .vyt-cal-day.is-past { color: #d4d4d4; cursor: not-allowed; pointer-events: none; }
    .vyt-cal-day:not(.is-empty):not(.is-past):hover { background: #f5f3ff; }
    .vyt-cal-day.is-from, .vyt-cal-day.is-to {
        background: var(--gradient); color: #fff;
    }
    .vyt-cal-day.is-between { background: #faf0fb; border-radius: 0; }
    .vyt-cal-controls {
        display: flex; justify-content: space-between; align-items: center;
        margin-bottom: 10px;
    }
    .vyt-cal-nav {
        background: transparent; border: 1px solid var(--line); border-radius: 999px;
        width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;
        cursor: pointer; color: var(--ink); font-size: 18px; line-height: 1;
    }
    .vyt-cal-nav:hover { background: #faf7ff; border-color: var(--purple); color: var(--purple); }

    /* ── Guests popover rows ───────────────────────────────────────────────── */
    .vyt-guests-row {
        display: flex; justify-content: space-between; align-items: center;
        padding: 14px 0; border-top: 1px solid var(--line);
    }
    .vyt-guests-row:first-of-type { border-top: 0; }
    .vyt-guests-row strong { display: block; font-size: 15px; }
    .vyt-guests-row span { display: block; font-size: 12px; color: var(--muted); }
    .vyt-guests-stepper {
        display: flex; align-items: center; gap: 14px; font-weight: 600;
        font-variant-numeric: tabular-nums;
    }
    .vyt-guests-btn {
        background: transparent; border: 1px solid var(--line); border-radius: 50%;
        width: 32px; height: 32px; cursor: pointer; font-size: 18px;
        color: var(--ink); line-height: 1;
        transition: border-color .1s, color .1s, opacity .1s;
    }
    .vyt-guests-btn:hover:not(:disabled) { border-color: var(--purple); color: var(--purple); }
    .vyt-guests-btn:disabled { opacity: .35; cursor: not-allowed; }
</style>
