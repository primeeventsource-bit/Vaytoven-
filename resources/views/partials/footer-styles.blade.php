{{--
  Styles for partials/site-footer.

  These lived inline in welcome.blade.php, which is where the footer used to
  live too. When the footer was extracted into a shared partial the CSS stayed
  behind, so every page rendered through layouts/site — /contact, /about,
  /careers, /press, /help and the rest — shipped the footer markup with no rules
  to style it: white background, ungridded columns, links in browser default
  blue. The homepage looked right, which is exactly why it went unnoticed.

  Both layouts include this partial now. It depends on --ink and --section-x
  from partials/brand-styles.
--}}
<style>
    .footer {
        background: var(--ink); color: rgba(255,255,255,.78);
        padding: clamp(3rem, 6vw, 5rem) var(--section-x, 24px) 2rem;
    }
    .footer-grid {
        display: grid; grid-template-columns: 1.4fr 1fr 1fr 1fr; gap: 48px;
        padding-bottom: 48px; border-bottom: 1px solid rgba(255,255,255,.1);
    }
    .footer-grid h5 { color: #fff; font-size: 13px; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; margin: 0 0 18px; }
    .footer-grid ul { list-style: none; padding: 0; margin: 0; display: grid; gap: 10px; font-size: 14px; }
    .footer-grid a { color: inherit; text-decoration: none; }
    .footer-grid a:hover { color: #fff; }
    .footer-brand .brand-mark-text { color: #fff; }
    .footer-brand p { font-size: 14px; max-width: 32ch; margin-top: 16px; color: rgba(255,255,255,.6); }
    .footer-brand address { font-style: normal; font-size: 14px; margin-top: 16px; color: rgba(255,255,255,.6); }
    /* welcome.blade.php sets `a { color: inherit; text-decoration: none }`
       globally, which would leave the email and phone looking like plain
       address text rather than something you can click. */
    .footer-brand address a { text-decoration: underline; text-underline-offset: 3px; }
    .footer-brand address a:hover { color: #fff; }
    .footer-bottom { padding-top: 24px; display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; font-size: 13px; color: rgba(255,255,255,.5); }
    .footer-bottom a { color: inherit; text-decoration: none; }
    .footer-bottom a:hover { color: #fff; }
    @media (max-width: 800px) { .footer-grid { grid-template-columns: 1fr 1fr; } }
    @media (max-width: 480px) { .footer-grid { grid-template-columns: 1fr; } }
</style>
