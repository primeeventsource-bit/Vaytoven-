# Vaytoven Rentals — Landing Page (WordPress)

This is the homepage at `vaytoven.com`. Every section below includes the structure, the copy, and a developer note for whoever is building the page in WordPress.

---

## Page structure

1. Hero with search
2. Featured destinations
3. Featured properties
4. Trust and safety
5. Become a host
6. App download
7. Testimonials
8. FAQ
9. Footer

Page goal: convert a stranger who landed from a Google search or paid ad into either a search session (guest) or a host signup. Everything on the page should serve one of those two outcomes.

---

## 1. Hero

**Layout:** full-bleed background image (warm-lit interior of a coastal villa at golden hour). Brand-gradient overlay at 30% opacity from bottom-left. Logo top-left, primary nav top-right, hero content vertically centered.

**Copy:**

> ### Find your place anywhere.
> Hand-picked stays from hosts you can trust — with payouts faster than the competition and support that actually picks up the phone.

**Search bar (sticky on scroll):**
- *Where to* — text input with Google Places autocomplete
- *Check in / Check out* — date range picker
- *Guests* — number stepper
- **[ Search ]** — primary button (pink → purple gradient)

**Below the search bar (small text):**
> Over 12,000 verified hosts across 84 markets.

**Dev note:** Submit POSTs to `https://app.vaytoven.com/search?location={...}&check_in={...}&check_out={...}&guests={...}`. Don't try to render search results in WordPress.

---

## 2. Featured destinations

**Layout:** horizontal scrollable row of 8 cards. Each card is a large rounded image with the city name overlaid bottom-left and a small property count.

**Copy:**

> ### Where travelers are heading this season

| City                | Tagline                              |
|---------------------|--------------------------------------|
| Miami, FL           | Beachfront stays, year-round sun    |
| Orlando, FL         | Family-friendly homes near the parks|
| Nashville, TN       | Music, bourbon, and front-porch nights|
| Lake Tahoe, CA      | Mountain cabins and alpine air      |
| Asheville, NC       | Smoky-mountain getaways             |
| Sedona, AZ          | Red-rock retreats                   |
| Bozeman, MT         | Big-sky escapes                     |
| Charleston, SC      | Coastal charm                       |

**Dev note:** Cards link to `app.vaytoven.com/search?location={city}`. Keep this list editable from the WP admin so the team can rotate seasonally.

---

## 3. Featured properties

**Layout:** 3-column grid (1-column on mobile). Each card shows the cover image, title, location, rating, and per-night price.

**Copy:**

> ### Stays our team would actually book
> Curated weekly by the Vaytoven editorial team. Every featured listing is verified, photographed to standard, and reviewed by humans.

**Dev note:** Pull featured listings via a simple JSON endpoint at `app.vaytoven.com/api/marketing/featured`. Cache for 1 hour. If the endpoint fails, fall back to a hard-coded list to avoid a broken hero on a marketing-site outage.

---

## 4. Trust and safety

**Layout:** 4 columns of icon + headline + 2-line description on a soft pink-tinted background.

**Copy:**

> ### Booking with confidence shouldn't be a luxury

| Icon       | Headline                | Body |
|------------|-------------------------|------|
| ✅ Shield   | Verified hosts          | Every host is identity-verified before they can accept payouts. No drive-by listings. |
| 🔒 Lock     | Secure payments         | Card details never touch our servers. Powered by Stripe — the same payment system used by Amazon and Google. |
| 💬 Bubble   | Real human support      | A live person on the line within an average of 4 minutes. No chatbot loops. |
| 🛡️ Badge   | 24-hour money-back guarantee | If your stay isn't what was advertised, we'll relocate you or refund you within 24 hours. |

**Dev note:** Use SVG icons in the brand pink → purple gradient. Don't use emoji in production; emoji is just shorthand here.

---

## 5. Become a host

**Layout:** half-width image (a host sitting on the porch of their listing, laptop open) on the left; copy and CTA on the right.

**Copy:**

> ### Already have a place? Earn more, faster.
>
> Vaytoven hosts keep more of every booking. We charge a flat 3% host fee — about half of what the big guys take — and we pay out 24 hours after check-in instead of holding your money for a week.
>
> A typical 2-bedroom in our top markets earns **$2,400–$3,800/month** on Vaytoven.
>
> **[ List your place ]**  ← primary CTA, pink → purple gradient
>
> *No long-term contracts. Pause your listing whenever life happens.*

**Below the CTA, three quick stats:**

| 3% | 24h | Avg. 4.9★ |
|---|---|---|
| Host fee. Half the industry standard. | From check-in to payout. | What our hosts rate us. |

**Dev note:** "Avg. 4.9★" is a placeholder. Replace with the actual host-NPS-derived number once 100+ hosts have rated us. Don't ship made-up stats.

---

## 6. App download

**Layout:** dark gradient panel (deep purple), phone mockup on the left showing the iOS app, copy + buttons on the right.

**Copy:**

> ### Take Vaytoven with you.
> Search, book, and message hosts on the go. Save listings to your wishlist and get notified when prices drop.
>
> [ Download on the App Store ]  [ Get it on Google Play ]

**Dev note:** Don't link these buttons to the marketing pages on Apple/Google until the apps are actually approved. Until then, the buttons should open a modal: "Launching soon — sign up to be notified" with a single email field.

---

## 7. Testimonials

**Layout:** carousel of 6 testimonials, 3 visible at desktop, 1 visible at mobile.

**Copy template (replace with real ones before launch):**

> ### Travelers and hosts on Vaytoven

**Testimonial 1 — Guest**
> "First time using Vaytoven and it became my new default. The host had clearly been vetted — the place looked exactly like the photos, which has not been my experience elsewhere."
> — Sarah K., Atlanta, GA

**Testimonial 2 — Host**
> "Switched from a competitor and added an extra $1,100 to my monthly take-home, just from the lower fee and faster payouts."
> — Marcus T., Miami, FL

**Testimonial 3 — Guest**
> "Had a small issue with the wifi and someone from Vaytoven called me back within five minutes. Five. Minutes. I almost dropped the phone."
> — Priya N., Austin, TX

**Dev note:** Do not ship the launch site with placeholder testimonials. If real ones aren't ready, hide this whole section and add it post-beta. Fake quotes are a brand-trust own-goal.

---

## 8. FAQ

**Layout:** accordion list on a white background.

**Copy:**

> ### Frequently asked questions

**How does Vaytoven verify hosts?**
Every host is identity-verified through Stripe before they can receive a payout. We also manually review the first listing photos before a property goes live.

**What happens if something goes wrong with my stay?**
Contact our support team within 24 hours of check-in. If your stay isn't substantially as described, we'll relocate you or refund you. Our team is reachable 7 days a week.

**How do I cancel a booking?**
Each listing displays its cancellation policy (Flexible, Moderate, or Strict) before you book. You can cancel from your Trips page, and refunds are processed automatically based on the policy.

**How much does it cost to list my place?**
Listing is free. Vaytoven takes a 3% host fee on each completed booking — that's it. No subscription, no listing fees, no surprises.

**When do hosts get paid?**
24 hours after the guest checks in. Payouts go directly to your bank account through Stripe.

**Is Vaytoven available in my area?**
We're currently active in 84 markets across the U.S. Add your city — we'll let you know when we're live near you.

**Dev note:** This is also a great place for SEO long-tail keywords. As the marketing team writes blog content, link the most-searched questions back here.

---

## 9. Footer

**Layout:** 4-column footer on a near-black background, with the logo and tagline above the columns.

**Columns:**

**Discover**
- Search stays
- Featured destinations
- Gift cards
- Mobile app

**Hosting**
- List your place
- Host resources
- Insurance and protection
- Hosting fees

**Company**
- About
- Press
- Careers
- Blog

**Help and legal**
- Help center
- Trust and safety
- Terms of service
- Privacy policy
- Do not sell my info (CCPA)

**Below the columns:**
- Social: Instagram, TikTok, X, YouTube, Facebook, LinkedIn
- Language and currency selectors
- © 2026 Vaytoven Rentals LLC. All rights reserved.

---

## SEO and metadata

**Title tag:** `Vaytoven Rentals — Find your place anywhere.`
**Meta description:** `Book hand-picked vacation rentals from verified hosts. Faster payouts, lower fees, real support. Cabins, beachfront homes, and city stays across the U.S.`
**OG image:** 1200×630, brand gradient with logo and tagline.
**Schema.org:** mark up the homepage as `WebSite` with a `SearchAction` pointing to `app.vaytoven.com/search?q={search_term_string}`.

---

## Performance budget

This page should hit the following targets on Google PageSpeed Insights (mobile):

- LCP under 2.0 s
- CLS under 0.05
- INP under 150 ms
- Total page weight under 1.5 MB (excluding videos)

Images use modern formats (WebP/AVIF), lazy-load below the fold, and are served via Cloudflare. The fonts are self-hosted (don't pull from Google Fonts at runtime).

---

## Conversion tracking

- Hero search submit → `event: marketing.search_started`
- Become-a-host CTA click → `event: marketing.host_cta_clicked`
- App download button click → `event: marketing.app_download_clicked` (with platform)
- FAQ accordion open → `event: marketing.faq_opened` (with question)
- Footer Privacy / Terms click → `event: marketing.legal_clicked`

Send all events to GA4 and to your own analytics endpoint at `app.vaytoven.com/api/marketing/events`. The GA4 leg is for the team's dashboards; the first-party leg is for retargeting and the eventual replacement of GA when policy changes force you to.
