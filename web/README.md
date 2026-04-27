# `web/` — Marketing landing page

The Vaytoven marketing site. A single self-contained HTML file with no build step, no JavaScript framework, and no external dependencies (except web fonts loaded from Google Fonts).

## Run it

```bash
open index.html
# or
python3 -m http.server 8080
```

## Deploy it

Drop `index.html` on any static host:
- Cloudflare Pages
- Netlify (drag-and-drop deploy)
- AWS S3 + CloudFront
- GitHub Pages

The page is roughly 84 KB unminified, single HTTP request, no third-party JS.

## What's on the page

1. **Hero** with search bar (decorative — links into the app)
2. **5 destination cards**
3. **4 featured property cards** with pricing
4. **Trust & safety section**
5. **Host CTA** with live earnings calculator
6. **Members section** — Managed Listing Program for vacation property points owners
7. **App download** with phone mockup
8. **3 testimonials**
9. **6+ FAQ accordions**
10. **Dark footer**

## Members section + modal

The Members section opens a modal that captures lead info for the Managed Listing Program (see SRS §3.9). Form fields:

- Name, email, phone (required)
- Vacation club / program (12-option dropdown including Marriott Vacation Club, Hilton Grand Vacations, Disney Vacation Club, RCI Points, Interval International, etc.)
- Property name & location (required)
- Annual points balance (optional)
- Best time to call (optional)
- Notes (optional)
- Consent checkbox (required)

On submit, the modal currently logs to console. To wire it to the real backend, replace the form-submit handler with a `fetch('/api/v1/members-enquiries', { method: 'POST', body: JSON.stringify(...) })` call.

## Language convention

This page NEVER uses the legal industry term "timeshare." Use "vacation property," "vacation club," "points-based ownership," or "member" instead. See repo root `README.md` for the full list and the rationale (FR-9.8).
