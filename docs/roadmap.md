# Vaytoven Rentals — Implementation Roadmap

Single-source plan to take the repo from its current state (~10% of the master prompt's vision) to MVP launch.
Generated 2026-05-06 against `Master Implementation Prompt`. Source of truth for sequencing — update this file in the same PR as the work it describes.

> **Branch policy:** all work lands on `development` first, gets verified end-to-end on https://v-app-dev-main-oyo1n9.laravel.cloud, then merges forward to `sandbox` and `production` as a deliberate step. Per saved feedback, never bundle "build new + retire old" — verification is its own step.

---

## 0. Reality vs vision

| Area | Master prompt expects | Repo today |
|---|---|---|
| Database driver | PostgreSQL 15 + PostGIS | **MySQL on Laravel Cloud** (`.env.example` says `pgsql` — mismatch overridden by Cloud env vars) |
| Schema | 43 tables documented in `docs/schema.sql` | 3 custom tables (contracts, contract_events, members_enquiries) + Laravel defaults (users, sessions, cache, jobs) |
| Models | Property, Booking, Amenity, MessageThread, Wishlist, Dispute, ~20 more | Contract, ContractEvent, MemberEnquiry, User |
| API | `/api/v1` Sanctum REST | Web routes only; no `routes/api.php`, no Sanctum |
| Frontends | `web/` `app/` `admin/` (React 18 single-file artifacts) | Flat Blade structure; rich `welcome.blade.php` landing page (1214 LOC) |
| Services | DocuSign, SupportChat, Tracking, LoginTracking, ChargebackCertificate, Members, Pricing, ten payment processors | DocuSign only |
| Tests | Booking collision, pricing math, refund policies, webhook idempotency, hash-chain tamper, anomaly detection, prompt injection, append-only enforcement | 7 feature tests (auth + profile + smoke + member enquiry) |
| Docs | SRS.md, schema.sql, architecture.md, roadmap.md, AGENTS.md | DocuSign + deployment runbooks only |

**The build is genuinely 80–90% smaller than the master prompt assumes.** The skeleton it references (`vaytoven_repo.zip`) is not in this repo. This roadmap treats the master prompt as the *target vision* and breaks the gap into shippable phases.

---

## 1. Architecture decisions to lock before any phase starts

These four decisions block everything else; defer them and the work has to be redone later.

### 1.1 MySQL or Postgres?

**Recommendation: stay on MySQL.** Laravel Cloud is provisioned, deployed, working, and on MySQL 8.4. Migrating to Postgres now means re-provisioning, re-attaching schemas, re-credentialing, re-debugging ProxySQL, and rewriting every migration. Cost outweighs benefit at MVP.

Implications of staying on MySQL:
- **PostGIS → MySQL spatial** (`POINT`, `ST_Distance`, `ST_Within`) for geo search. Less mature than PostGIS but adequate for a Vaytoven-scale catalogue.
- **`EXCLUDE` constraint → MySQL `SELECT FOR UPDATE`** + a unique index on `(property_id, check_in, check_out)` validated in the app layer inside a row-locking transaction. Defense-in-depth, not perfect, but proven pattern (Eventbrite, Booking.com both use it on MySQL).
- **JSONB → JSON column** with `JSON_EXTRACT` / `->>` operator.
- **Append-only `tracking_events`** via MySQL trigger (`BEFORE UPDATE` / `BEFORE DELETE` raise `SIGNAL SQLSTATE`) — same outcome as Postgres trigger, different syntax.

Action: update `docs/architecture.md` to make MySQL the canonical choice and call out the EXCLUDE-constraint substitute. Update `.env.example` to `DB_CONNECTION=mysql`.

### 1.2 Folder structure

**Recommendation: keep flat Laravel layout, NOT split into `backend/` `web/` `app/` `admin/`.** Splitting now would require:
- Moving every existing file
- Reconfiguring composer autoload paths
- Updating Laravel Cloud's repository root
- Breaking every existing import

Adapt the master prompt's mental model to the flat layout:
- `web/` = `resources/views/` (Blade) + `public/` (static)
- `app/` (traveler/host React app) = a future `resources/js/app/` SPA mounted at `/app`
- `admin/` (operations console) = a future `resources/js/admin/` SPA mounted at `/admin`
- Backend = the Laravel app itself

### 1.3 Frontend strategy

**Recommendation: keep Blade for marketing, add Livewire for interactive surfaces, defer React.** Reasons:
- Livewire is already required (`livewire/livewire ^3.6`, `livewire/volt ^1.7`)
- Blade renders 1214-LOC landing page perfectly without a build step
- React 18 single-file artifacts (Babel-in-browser JSX) per the master prompt has terrible Lighthouse scores and locks future work into a custom build pipeline
- Livewire/Volt covers the "interactive but not SPA" use cases (booking modal, host dashboard, admin queue) without committing to a JS framework migration

If a future PM decides React is mandatory for the booking flow, swap Livewire for Inertia + React then; the controller layer stays the same.

### 1.4 Auth

**Recommendation: Breeze (web sessions) + Sanctum (API tokens for mobile in Phase 2).** Breeze is already installed. Add `laravel/sanctum` when the API layer ships in Phase 4.

---

## 2. Phased plan — 13 phases, ~6–10 weeks of single-developer effort

Each phase produces a shippable, deployable deliverable. Each ends with a green `development` deploy and explicit acceptance criteria. Phases are mostly sequential; parallelization opportunities are flagged.

### Phase 0 — Foundation & agent briefings *(half-day)*

The minimum scaffolding so future AI sessions and human contributors have proper context.

**Deliverables**
- `AGENTS.md` (the rulebook the master prompt assumes exists)
- `.cursorrules` and `.github/copilot-instructions.md` (auto-loaded by their respective tools)
- `docs/SRS.md` (FR-1.x through FR-11.x stub structure, fill in as features ship)
- `docs/schema.sql` (canonical schema doc, starts with what exists, grows per phase)
- `docs/architecture.md` (MySQL decision, folder layout, auth, frontend strategy)
- `.env.example` updated: `DB_CONNECTION=mysql`, `SLACK_MEMBERS_WEBHOOK=`, `ANTHROPIC_API_KEY=`, `MAXMIND_LICENSE_KEY=`, etc. (env vars referenced in master prompt)

**Acceptance:** `cat AGENTS.md docs/SRS.md docs/schema.sql docs/architecture.md` returns coherent content. `composer install && php artisan test` still passes.

---

### Phase 1 — Authorization & roles *(half-day)*

Right now `/admin/contracts` is gated by `auth` middleware only — any registered user can reach it. Master prompt requires real RBAC.

**Deliverables**
- Migration: add `role` enum to `users` (`traveler` | `host` | `member` | `admin` | `super_admin`)
- `App\Http\Middleware\EnsureAdmin` — checks `$request->user()->role === 'admin'` or higher
- Register middleware alias `admin` in `bootstrap/app.php`
- Update `routes/web.php` admin group to `->middleware(['auth', 'admin'])`
- Promote the existing Master Admin user (id=1 on dev) to `role='super_admin'`
- Tests: `AdminMiddlewareTest` — non-admin gets 403, admin gets 200, unauthenticated redirects to /login

**Acceptance:** logging in as a fresh registered user and visiting `/admin/contracts` returns 403; logging in as Master Admin returns the contracts index.

---

### Phase 2 — Core schema (the 43 tables) *(3–5 days)*

The biggest single phase. Translates `docs/schema.sql` (which we'll author here) into MySQL migrations. Built in dependency order.

**Deliverables**
- `properties`, `property_amenities`, `amenities`, `property_photos`
- `bookings`, `booking_state_transitions`, `cancellation_policies`
- `payment_intents`, `charges`, `refunds`, `payouts`
- `reviews`, `review_responses`, `wishlists`, `wishlist_properties`
- `message_threads`, `messages`
- `disputes`, `dispute_evidence`
- `tracking_events` (with append-only trigger), `ppc_visitors`, `chargeback_disputes`
- `support_chat_sessions`, `support_messages`, `support_tickets`, `support_ticket_messages`
- `login_sessions`, `terms_versions`, `terms_acceptances`
- `admin_audit_logs`
- `host_payout_accounts`, `member_specialist_assignments`
- Eloquent models with relationships for each
- Foreign keys, indexes, MySQL spatial column on `properties.location`
- A booking-overlap unique constraint adapter: stored procedure or app-layer transaction enforcing no `(property_id, daterange)` overlap
- Seeder `AmenitiesSeeder` (couches, beds, wifi, pool, etc.)

**Acceptance:** `php artisan migrate:fresh --seed` runs clean. All models can be `factory()->create()`'d. Booking overlap test (two simultaneous bookings on same property/dates) — one succeeds, one rejects with `BookingConflictException`.

**Parallelizable:** sub-groups (auth-related, booking-related, payments-related, support-related, tracking-related) can be implemented by parallel agents in worktrees, then merged.

---

### Phase 3 — REST API foundation *(2 days)*

Add Sanctum + `/api/v1` namespace + auth controllers + traveler-facing endpoints.

**Deliverables**
- `composer require laravel/sanctum`, publish config, run migration
- `routes/api.php` mounted in `bootstrap/app.php` under `/api/v1` with `auth:sanctum` middleware
- `Api\AuthController` (`POST /api/v1/auth/login`, `/auth/register`, `/auth/logout`, `/auth/me`)
- `Api\PropertyController` (search + show)
- `Api\BookingController` (index, store, cancel)
- API resources: `PropertyResource`, `BookingResource`, `UserResource`
- Form requests: `StoreBookingRequest`, `UpdatePropertyRequest`
- `X-Vaytoven-Surface` header captured into `request()->attributes` for downstream tracking
- Tests: API auth flow, booking creation, booking collision via API

**Acceptance:** `curl -X POST .../api/v1/auth/login` returns a token; `curl -H 'Authorization: Bearer ...' .../api/v1/bookings` lists the user's bookings.

---

### Phase 4 — Members (Managed Listing Program) — verify & finish *(1 day)*

Per master prompt: "verify and finish the remaining bits". The skeleton already exists in this repo.

**Deliverables**
- Verify `MemberEnquiryController` test passes against current schema
- Wire `App\Notifications\MembersEnquiryReceived` (Slack via `SLACK_MEMBERS_WEBHOOK` env var, email via `Mail\MembersEnquiryReceived`)
- Implement conversion path: when a specialist marks `enquiry.status='onboarded'`, create a `properties` row with `listing_source='managed'`, payout to escrow Stripe account, write `converted_property_id` back to enquiry
- Admin queue UI (Livewire/Volt component at `/admin/members`)
- Tests: form submission produces Slack alert + email + admin queue entry; specialist conversion creates managed property

**Acceptance:** `curl -X POST .../members/enquiry -d ...` produces a Slack message and confirmation email within 60 seconds; specialist marks onboarded, property appears in `properties`.

---

### Phase 5 — Stripe Connect + payments core *(3 days)*

Stripe SDK is already required; just wire it.

**Deliverables**
- `app/Services/Payments/StripeService` — payment intents, capture, refund, dispute fetch
- `app/Services/Payments/Stripe/WebhookHandler` — idempotent via `stripe_events` table (`event_id` unique, replays no-op)
- Webhook route `/webhooks/stripe` (CSRF-excluded)
- Handle: `payment_intent.succeeded`, `payment_intent.payment_failed`, `charge.refunded`, `charge.dispute.created`, `account.updated`
- Booking → PaymentIntent flow in `BookingService::checkout()`
- Refund math service: `RefundCalculator` with the four cancellation policies (flexible, moderate, strict, non_refundable) — pure function, integer cents
- Tests: `StripeWebhookIdempotencyTest`, `RefundCalculatorTest` covering all four policies × multiple time-from-checkin scenarios

**Acceptance:** A test-mode booking checks out, the `payment_intent.succeeded` webhook fires twice (replay), the second fires no-op. All four cancellation policies produce mathematically correct refunds for boundary cases.

**Parallelizable:** WebhookHandler + RefundCalculator + StripeService can each be implemented + tested in parallel.

---

### Phase 6 — Tracking events + chargeback evidence (FR-10.x) *(3–4 days)*

The append-only audit trail and evidence bundle.

**Deliverables**
- `tracking_events` schema with `parent_hash` and `current_hash` columns; trigger blocks UPDATE/DELETE; `current_hash = sha256(parent_hash || event_id || event_type || ...)`
- `app/Services/TrackingService::record(...)` — adds row, computes hash chain
- `app/Http/Controllers/Api/TrackingEventController` — `POST /api/v1/tracking/events`
- `public/vyt-track.js` — JS SDK that sets `vyt_vid` cookie, captures UTM/click-id into `vyt_utm` cookie (30-day), posts page-view events. Mounted on the marketing landing.
- `app/Services/Chargeback/EvidenceBundleService::generate($bookingId | $userId, $dateRange)` — returns JSON + PDF
- Per-processor adapters scaffolded in `app/Services/Payments/<processor>/DisputeAdapter.php` (10 stubs, Stripe wired; others throw `NotImplemented` for now)
- Admin route `/admin/disputes/{id}/evidence` — generates and downloads bundle
- Tests: append-only enforcement (UPDATE attempt throws), hash-chain verification (tampered row detected), evidence ordering (consumption first, passive last)

**Acceptance:** Generating an evidence bundle for a user with 30+ events completes in <5 seconds. A direct `UPDATE tracking_events SET ...` from MySQL CLI fails with the trigger error. Modifying a `current_hash` value breaks chain verification.

---

### Phase 7 — AI Support Chat (FR-11.x) *(2–3 days)*

Claude integration + widget + ticket queue.

**Deliverables**
- `composer require anthropic-ai/anthropic-sdk-php` (or use Guzzle directly per Anthropic SDK v2 patterns)
- `config/services.php` `anthropic` block, `ANTHROPIC_API_KEY` env var
- Schema: `support_chat_sessions`, `support_messages`, `support_tickets`, `support_ticket_messages` (Phase 2 created these — confirm)
- `app/Services/SupportChatService` with surface-specific tool definitions: `get_booking_status`, `get_recent_charges`, `search_help_articles`, `create_ticket`
- System prompt locks policy answers to `search_help_articles` only — refund/cancel/fee questions must call the tool first and quote it verbatim
- Help article store: stub with 6 articles for now (Algolia/Meilisearch is Phase 11)
- Widget: `public/vyt-chat.js` (vanilla JS, ~300 LOC), mounted on landing
- Admin queue: Livewire component at `/admin/support-tickets`
- Tests: prompt-injection refusal, missing-API-key graceful failure, rate limit (30/IP/min)

**Acceptance:** Logged-in user asks "where's my refund for VYT-K3M9P2" and gets a useful answer drawn from `get_booking_status`. User asking "can you cancel my booking?" gets escalated via `create_ticket`. Ticket appears in admin within seconds.

---

### Phase 8 — Login tracking + Activity Map *(2 days)*

The chargeback evidence system's user-side companion.

**Deliverables**
- Schema: `login_sessions` with geo/device fields; `terms_versions` + `terms_acceptances` (Phase 2 created — confirm)
- `app/Services/LoginTrackingService::recordLogin($user, $request)`
- `App\Listeners\TrackAuthEvents` listening to `Login`, `Logout`, `Failed`, `Lockout`
- Anomaly detection: new country, new device, geographic impossibility (>1000km in <1hr), known Tor exit (later, post-GeoIP)
- `app/Services/Chargeback/ChargebackCertificateService` — generates Service Usage Confirmation PDF using `spatie/laravel-pdf`
- `composer require spatie/laravel-pdf`
- Blade template `resources/views/certificates/usage-certificate.blade.php`
- Routes: `GET /me/login-history`, `/me/activity-map`, `/admin/users/{id}/login-history`, `/admin/users/{id}/certificate.pdf`
- Anomaly notification: email to user + tracking event `suspicious_login_flagged`
- Tests: anomaly detection (4 scenarios), terms version content addressing, PDF generation completes for 50+ records

**Acceptance:** Admin clicks "Download Certificate" for a disputed user, gets a PDF with login records, terms acceptances, and engagement events.

---

### Phase 9 — Real GeoIP wiring (FR-10 + FR-11 unblock) *(half-day)*

The single most-needed plumbing fix.

**Deliverables**
- Choose: MaxMind GeoIP2 (`geoip2/geoip2`, on-prem .mmdb, license-required, fast) **OR** ipinfo.io (cloud API, better VPN/datacenter detection)
- Recommendation: **MaxMind for prod** (GDPR-friendly, no third-party data hop), ipinfo.io as fallback for VPN detection only
- `app/Services/GeoIp/GeoIpService` — single shared interface, swappable provider
- Wire into `TrackingService::resolveIpGeo()` and `LoginTrackingService::geoLookup()`
- Redis cache, 7-day TTL keyed by IP
- Lookup failures don't block auth or tracking (return null, log warning)
- Tests: cache hit/miss, lookup failure tolerance

**Acceptance:** Login from a US IP populates `login_sessions.country='US'`, `region='CA'`, etc. Same IP twice in a row only hits MaxMind once.

---

### Phase 10 — Three-audience CTAs + tracking integration *(1 day)*

Wire the marketing-side conversion funnel to the tracking system.

**Deliverables**
- Add `members_modal_opened`, `members_form_started`, `members_form_submitted`, `members_specialist_contacted` events to the JS SDK
- Verify the existing modal in welcome.blade.php fires them
- Funnel report Livewire component at `/admin/funnels/members`

**Acceptance:** Filling out the Members modal and submitting produces 3 tracking events visible in the admin funnel report.

---

### Phase 11 — Production help center *(2–3 days)*

Replace the AI chat's help-article stub with real curated content.

**Deliverables**
- Choose: Algolia (managed, polished) OR Meilisearch (self-hosted, cheaper)
- Recommendation: **Meilisearch** for MVP (Laravel Cloud has Redis, can add Meilisearch as a Cache-style attached resource)
- ~40 articles authored: booking flow, payments, cancellations, hosting, Managed Listing Program, trust & safety, account
- Each article: `{slug, title, body_md, category, last_reviewed_at}`
- Indexed via `scout` or direct Meilisearch SDK
- `SupportChatService::toolSearchHelp()` swapped from stub to real query
- `/help/{slug}` Blade route for users to browse directly
- Tests: search returns expected articles, AI quotes verbatim from results

**Acceptance:** Asking the AI "what's your cancellation policy on flexible bookings?" produces an answer that quotes the help article verbatim, with a link to `/help/cancellation-policies`.

---

### Phase 12 — Per-processor dispute adapters *(1 day each, parallelizable)*

10 processors, 10 adapters. Stripe is done in Phase 5/6.

**Deliverables (per adapter)**
- Format the evidence bundle into the processor's submission format
- Submit via API (Stripe, Authorize.Net, NMI, Nuvei) or generate a PDF for portal upload (Merchant E Solutions, Payment Cloud, EMS, Nexio, Netevia, Kurv)
- Tests: bundle formatting matches processor expectations; submission API responses handled

**Acceptance:** Each adapter produces output the corresponding processor portal accepts (verified manually for the 6 PDF ones; verified via API sandbox for the 4 with APIs).

**Parallelizable:** all 10 adapters are independent; spawn 10 parallel agents.

---

### Phase 13 — Legal docs + Terms acceptance flow *(parallel with all phases — counsel-bound)*

**Deliverables**
- Drafts authored: Terms of Service, Chargeback Policy, Privacy Policy, Member Agreement
- `app/Models/TermsVersion` with `forContent($kind, $content, $url, $versionLabel)` content-addressing
- Migration: `terms_versions.content_hash` = SHA-256 of canonical text
- "Accept again" flow: middleware that checks current `TermsVersion` against user's most-recent acceptance and forces an interstitial
- Acceptance: trigger on signup, checkout, and version change

**Acceptance:** Replacing a Terms version's content updates `current_hash`; users with prior acceptances get an "accept again" prompt on next login.

---

## 3. Risk register

| Risk | Mitigation |
|---|---|
| Postgres-only features (PostGIS, EXCLUDE, JSONB) used in master prompt | Documented MySQL substitutes in `docs/architecture.md` (Phase 0) |
| 43-table schema explosion creates merge conflicts | Phase 2 split into sub-groups (auth, booking, payments, support, tracking) — parallelizable |
| Stripe webhook idempotency bugs leak duplicate charges | Mandatory `stripe_events` unique-event-id table + replay test in CI |
| AI chat prompt injection extracts other users' data | All tools accept `auth()->user()` only; no user_id in tool args; tests assert isolation |
| `tracking_events` performance at scale | Daily partitioning by `event_date`; archive >90d to cold storage |
| GeoIP lookup latency on hot login path | Redis cache + async record (push to queue, ack login immediately) |
| MySQL booking overlap race condition | `SELECT ... FOR UPDATE` in transaction, plus `UNIQUE KEY (property_id, check_in_date, check_out_date)` for cheap duplicate detection |
| Counsel review blocks launch | Phase 13 runs in parallel; placeholder content lets feature work proceed |
| Trademark warning (Vaytoven not cleared) | Counsel review before any branding spend; do NOT incorporate before |

---

## 4. Parallelization plan

For maximum speed, the following work can run in parallel agents/worktrees:

- **Phase 2 sub-groups** (5 parallel migration tracks)
- **Phase 5/6 within payments** (StripeService, WebhookHandler, RefundCalculator independent)
- **Phase 7 within chat** (Service, widget, ticket queue independent)
- **Phase 12 across all 10 processor adapters**

A typical day-of-work could spawn:
- 1 Plan agent per phase to detail the next 2 phases
- 3–5 implementation agents in parallel worktrees
- 1 review agent verifying the merge of completed work

---

## 5. Sequencing recommendation

**Critical path:** 0 → 1 → 2 → 3 → 5 → 6 → 9 → 7 → 8 → 4 → 10 → 11 → 13 → 12

Reasoning: Foundation (0, 1) → schema (2) → API (3) → payments (5) before evidence (6) so tests have real charge data → GeoIP (9) before chat polish (7) so login events have full geo → login tracking (8) once chat infra exists → finish Members (4) which depends on conversion path needing properties/payouts from earlier phases → marketing CTAs (10) → help center (11) → terms (13) finalized → processor adapters (12) parallelized last.

**Estimated total:** 6–10 weeks single-developer, 3–4 weeks with 3 parallel agents on Phase 2 + Phase 5/6 + Phase 12.

---

## 6. What ships first (next concrete commit)

**Phase 0** — agent briefings + docs scaffolding + `.env.example` fix. ~half a day. Zero behavior change. Sets up everything else.

After Phase 0 deploys cleanly to https://v-app-dev-main-oyo1n9.laravel.cloud, kick off Phase 1 (RBAC) and Phase 2 (schema buildout) in parallel.
