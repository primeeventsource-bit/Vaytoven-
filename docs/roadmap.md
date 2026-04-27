# Vaytoven Rentals — Build Roadmap

**A 22-week plan to go from zero to a publicly bookable marketplace.**

This is a working roadmap, not a marketing timeline. Slip will happen. The point is to know which week you're slipping in and why, so you can re-plan instead of panic.

---

## Reading this roadmap

- **One full-time developer** is the assumed staffing. Add a second engineer and you can compress weeks 7–18 by roughly 30%; you can't usefully compress weeks 1–6 because they're sequential.
- Each phase has an **exit criterion** — a check you have to be able to make before the next phase starts. If you can't, finish the current phase before moving on.
- Anything labeled **(deferrable)** can slide into a fast-follow release without blocking launch.

---

## Phase 0 — Pre-flight (Week 0, ~3 days)

Before any code is written.

- [ ] Verify the name is actually clear: USPTO TESS search, domain registration, social handles on Instagram, TikTok, X, YouTube, Threads.
- [ ] Register the legal entity (Vaytoven Rentals LLC).
- [ ] Open business bank account.
- [ ] Set up Apple Developer ($99/yr) and Google Play Developer ($25 one-time) accounts under the LLC.
- [ ] Stripe account application submitted (approval can take 1–2 weeks; start now).
- [ ] Provision the AWS organization, root account MFA, and a billing alarm at $200/month.

**Exit:** entity formed, bank account open, all three developer/payment account applications submitted.

---

## Phase 1 — Planning and design (Weeks 1–2)

| Week | Deliverable |
|---|---|
| 1 | Final SRS sign-off. Locked feature scope. Brand guide finalized (colors, type, logo). |
| 1 | Figma design system: color tokens, typography, base components (button, input, card, modal). |
| 2 | Hi-fi screens for the 12 critical flows (signup, search, listing detail, checkout, host onboard, calendar, payouts, reviews, messages, admin queue, admin user, security log). |
| 2 | Schema review session — walk through `01_schema.sql` line by line with whoever is on the team. |
| 2 | API contract published as OpenAPI 3.1 spec — gives the mobile devs something to mock against. |

**Exit:** every screen the user will see in v1 exists in Figma at hi-fi. Every endpoint the apps will call is documented with example request/response.

---

## Phase 2 — MVP backend (Weeks 3–6)

| Week | Deliverable |
|---|---|
| 3 | Laravel project scaffolded. Auth (email + password, email verification, password reset). User and role tables migrated and seeded. |
| 3 | CI pipeline (GitHub Actions): lint, type-check, run unit tests, run feature tests against a Postgres service container. |
| 4 | Properties module: CRUD, photo uploads via S3 pre-signed URLs, amenities, calendar overrides. |
| 4 | Search endpoint (PostGIS query). Acceptance: 10k seeded listings, p95 < 800 ms. |
| 5 | Bookings module: quote → request → confirm → cancel state machine with the gist exclusion constraint enforced. |
| 5 | Reviews module + double-blind release window (scheduled job). |
| 6 | Messaging module + masking rules for phone/URL in pre-booking threads. |
| 6 | Notifications module — email channel only at this stage (SendGrid). |

**Exit:** a tester can register, list a property, book it as a different user, leave a review, and message — all via API calls in Postman. Auth is solid enough to demo without embarrassment.

---

## Phase 3 — Web frontend (Weeks 7–10)

| Week | Deliverable |
|---|---|
| 7  | App shell, layout, navigation, design-system components ported to Blade + Vue islands (or Inertia, whichever the team picks). |
| 7  | Public search page: list view + map view + filter panel. |
| 8  | Listing detail page. Calendar component. Photo gallery. |
| 8  | Auth screens: signup, login, password reset, 2FA setup, security/sessions page. |
| 9  | Guest dashboard: trips, wishlists, messages, payment methods. |
| 9  | Host dashboard: listings index, listing wizard (8-step), calendar editor, earnings. |
| 10 | Admin console: user search, listing approval queue, moderation queue, audit log viewer. |
| 10 | End-to-end smoke tests with Cypress on the 5 happy paths. |

**Exit:** every page exists, every form posts to a real endpoint, and the lighthouse score on the search page is ≥ 85 on mobile.

---

## Phase 4 — Booking and payments (Weeks 11–14)

| Week | Deliverable |
|---|---|
| 11 | Stripe Connect Express onboarding integrated into the host wizard. |
| 11 | Stripe Elements integrated on checkout. PaymentIntents created server-side with idempotency keys. |
| 12 | Webhook handler — payment_intent.succeeded, charge.refunded, account.updated, payout.paid, payout.failed. Idempotent and signed. |
| 12 | Refund flow on cancel (per cancellation policy). |
| 13 | Payout scheduler. Created on `booking.completed + 24h`. Surfaces failures back to the host. |
| 13 | Tax handling for the launch market (probably a flat occupancy tax for v1; full TaxJar integration is P2). |
| 14 | End-to-end payment tests using Stripe test mode. Decline flows verified. Webhook replay verified. |
| 14 | First **internal closed beta** — the team books real stays at each other's properties using real cards on test mode. |

**Exit:** money flows correctly in, correctly out, correctly back on cancel. Webhook replay storms don't double-charge. You can produce a clean reconciliation between Stripe's dashboard and the `payments` + `payouts` tables.

---

## Phase 5 — Dashboards, security hardening, and admin (Weeks 15–18)

| Week | Deliverable |
|---|---|
| 15 | Login security pipeline: risk scoring, suspicious-login detection, security email on flagged events. |
| 15 | Active-sessions page with remote logout. |
| 16 | Admin dispute resolution flow + reason codes. |
| 16 | Admin audit log viewer with filter and export. |
| 17 | Notifications: SMS (Twilio) and push (APNs/FCM) channels added. Per-user preferences page. |
| 17 | Performance pass: query review, N+1 sweep, image optimization, CDN headers, cache layer for hot reads. |
| 18 | External penetration test (commission a 1-week engagement). |
| 18 | Triage and remediate every high/critical finding from the pentest. |

**Exit:** zero open critical security findings. p95 latency targets met under synthetic load. Admins can resolve a dispute end-to-end without engineering help.

---

## Phase 6 — Mobile API hardening + mobile MVP (Weeks 19–22)

| Week | Deliverable |
|---|---|
| 19 | API hardening for mobile: refresh-token rotation, push-token registry, deep-link handler, App Store / Play asset pipeline. |
| 19 | iOS skeleton: splash, signup, login, home/search, listing detail, checkout, trips, profile, inbox, push registration. |
| 20 | iOS Host Mode: listing list, calendar, messages. |
| 20 | iOS internal TestFlight build with seeded test accounts. |
| 21 | Android skeleton — same scope as iOS Week 19. |
| 21 | Android internal track build. |
| 22 | App Store and Play submission. |
| 22 | Public marketing site goes live. Begin private beta of web app with first 50 hand-picked hosts. |

**Exit:** apps reviewed and approved. Web app open to a controlled invite cohort. Booking flow works on every supported device class.

---

## Launch checklist (Week 23+)

Treat launch as a separate sprint, not a victory lap. Go through every line.

### Engineering

- [ ] All migrations have rollback paths.
- [ ] Backups verified by restoring to a fresh RDS instance and querying at least one row per table.
- [ ] Monitoring dashboards green for 7 consecutive days under synthetic load.
- [ ] Alert pager (PagerDuty or OpsGenie) wired to the on-call engineer.
- [ ] Status page deployed at status.vaytoven.com.
- [ ] Runbooks written for: payment outage, Stripe webhook failure, search latency spike, mass-cancel scenario.

### Trust and safety

- [ ] All listings in the launch market manually approved.
- [ ] Identity verification required before payouts above $1,000 cumulative.
- [ ] First-line support staff trained on dispute resolution and refund authority.
- [ ] Anti-fraud rules in place: velocity checks on signups, BIN-country mismatches, impossible-travel.

### Legal and finance

- [ ] Privacy Policy v1 and Terms of Service v1 published, versioned in DB.
- [ ] CCPA "Do Not Sell" link present in the footer (even if we don't sell, the link is required in California).
- [ ] DPAs signed with all sub-processors (Stripe, AWS, SendGrid, Twilio, IPinfo).
- [ ] Sales-tax determination per launch state documented and signed off.
- [ ] Insurance: at minimum a tech E&O policy and cyber liability. Host damage protection is a separate underwriting question — confirm whether it's bundled or excluded at launch.

### Marketing

- [ ] Landing page live with working signup capture.
- [ ] Email sequences set up: welcome (guest), welcome (host), abandoned-checkout, pre-stay reminder, post-stay review nudge.
- [ ] Press kit available at /press.
- [ ] App Store and Play listings populated with screenshots, keywords, descriptions.

---

## MVP feature list — three buckets

### Must-have at launch (MVP)

- Email/password signup, email verification, password reset, 2FA optional.
- Property listing wizard with min-5-photo requirement.
- Admin listing review queue.
- Search by location + dates + guests, filters, list and map views.
- Booking with Instant Book or request-to-book.
- Stripe checkout, Stripe Connect host onboarding, scheduled payouts.
- Cancellation policies (Flexible / Moderate / Strict).
- Reviews (double-blind, 14-day window).
- Messaging tied to bookings.
- Email + transactional SMS notifications.
- Wishlists.
- Admin moderation queue, audit log, dispute resolution.
- Login activity log + active sessions + suspicious-login detection.
- iOS and Android app with feature parity on the 9 core screens.

### Phase 2 (first 90 days post-launch)

- iCal sync (import availability from Airbnb/Vrbo so dual-listed hosts don't double-book).
- Saved-search alerts.
- Push-notification preferences UI per type.
- Host damage-protection program.
- Multi-currency display.
- More languages (Spanish first if launching in Florida).
- In-app help center articles.
- Referral program for guests and hosts.

### Phase 3 (advanced, after product-market fit)

- Smart pricing recommendations based on local demand.
- Co-host roles (multiple users managing one listing).
- Long-term stays (28+ nights) with discounted pricing.
- Experiences/activities marketplace.
- Public API for property management software vendors.
- ML-driven search personalization.
- Trip insurance partnership.

---

## Recommended tools and packages

### Laravel ecosystem

| Need | Package |
|---|---|
| Auth & API tokens                       | `laravel/sanctum` (web) + custom JWT (mobile) |
| Background jobs + dashboard             | `laravel/horizon` |
| Stripe                                  | `stripe/stripe-php` (use the SDK directly; skip Cashier — too opinionated for marketplaces) |
| Image processing                        | `intervention/image` |
| Pre-signed S3 URLs                      | `aws/aws-sdk-php` (no wrapper needed) |
| API resources / serialization           | Built-in `JsonResource` |
| Permissions                             | `spatie/laravel-permission` |
| Activity log (for users — not the same as admin_audit_logs) | `spatie/laravel-activitylog` |
| Search (when PostGIS is no longer enough) | `laravel/scout` + Meilisearch |
| OpenAPI spec generation                 | `vyuldashev/laravel-openapi` or hand-write the YAML — both are fine |
| Geo & ICS calendars                     | `clue/ndjson-react` for iCal streams; `nesbot/carbon` is built-in |
| Testing                                 | `pestphp/pest` (more readable than PHPUnit, fully compatible) |
| Static analysis                         | `larastan/larastan` and `laravel/pint` |

### WordPress (marketing site only)

| Need | Plugin |
|---|---|
| Performance + caching          | LiteSpeed Cache or WP Rocket |
| SEO                            | RankMath (lighter than Yoast Pro for the marketing site's needs) |
| Forms (lead capture)           | Fluent Forms or Gravity Forms |
| Cookie/consent banner          | CookieYes or Iubenda |
| Image optimization             | ShortPixel or Imagify |
| Security                       | Wordfence (block-only mode; don't run their malware scanner during high traffic) |
| Page builder (if needed)       | Bricks Builder if developer-driven; Elementor if not |

Keep this site small. Resist the urge to build app features in WordPress.

### Payments

- **Stripe** — Payments, Connect (Express accounts), Identity if you skip a separate KYC vendor, Tax (P2).
- Don't take ACH directly at launch. Stripe handles bank payouts to hosts; that's enough.

### Maps and geo

- **Google Maps Platform** for geocoding, place autocomplete, and map tiles. Restrict the API key to your domains.
- **PostGIS** server-side. It is genuinely the right answer; don't over-engineer with Elasticsearch yet.
- **Mapbox** is a great alternative if Google's pricing surprises you at scale; the swap is straightforward because you'll wrap maps behind a thin client component.

### IP geolocation

- **IPinfo** (preferred) or **MaxMind GeoIP2**. Both have offline databases you can self-host if you want sub-millisecond lookups and want to avoid an external dependency on the login path.

### Auth and security

- **Stripe Identity** or **Persona** for government-ID verification.
- **hCaptcha** (more privacy-friendly than reCAPTCHA) on signup and password reset.
- **WebAuthn** (passkeys) is on the P2 list — it's worth doing.
- **Sentry** for error tracking; configure PII scrubbing.
- **AWS WAF** + **Cloudflare** for edge filtering and rate limiting before traffic hits the app.

### Hosting and infra

- **AWS**: ECS Fargate, RDS Postgres (Multi-AZ), ElastiCache Redis, S3, CloudFront, Secrets Manager, Route53.
- **Cloudflare** in front for DNS, edge caching of static assets, and DDoS protection.
- **Terraform** for everything you provision. No clicking in the AWS console.
- **GitHub Actions** for CI/CD.

### Mobile

- **iOS:** SwiftUI, async/await networking, Keychain for token storage, APNs for push.
- **Android:** Jetpack Compose, Kotlin coroutines, EncryptedSharedPreferences for token storage, FCM for push.
- **Why not React Native or Flutter?** They're fine choices, but with a single developer and tight scope, native gets you better App Store review experience and zero "weird bugs nobody else has." Revisit cross-platform when you have a 3+ person mobile team.

### Notification delivery

- **SendGrid** (or **Postmark** for transactional emails — Postmark is faster and smaller; SendGrid scales further).
- **Twilio** for SMS.
- **APNs** + **FCM** for push (no third-party push service needed at this scale).

### Observability

- **Sentry** — errors.
- **Datadog** or **Grafana Cloud** — metrics and APM. (Grafana is meaningfully cheaper at small scale.)
- **Logtail** or **CloudWatch Logs** — log aggregation.

---

## What can go wrong, and the early signal to watch for

| Signal | What it usually means | First response |
|---|---|---|
| Host signups stall after week 1 | Onboarding friction (Stripe Connect or photo upload). | Add a manual concierge for the first 100 hosts; instrument every step of the wizard. |
| Searches return zero results | Geocoding bugs, PostGIS index missed, supply density too low in launch market. | Lower the launch geographic scope; verify lat/lng on every approved listing. |
| Bookings created but payments stuck pending | Webhook misconfigured or rate-limited. | Verify webhook endpoint is reachable; replay the missed events from Stripe dashboard. |
| Sudden spike in suspicious-login flags | Credential-stuffing attack. | Tighten per-IP login rate limit; force MFA for all flagged accounts. |
| Apple or Google rejects the app | Almost always: marketplace fees, missing privacy disclosures, or login issues for the review account. | Provide a real review account with a real test booking ready; review their last rejection letter line by line. |

---

If your team has bandwidth for only one *cultural* commitment during this build, make it: **boring tech wins**. Use Postgres until it stops working. Use Laravel's queues until they stop working. Use email until users ask for SMS. Every dependency you don't add at launch is one you don't have to operate at 2 AM in month four.
