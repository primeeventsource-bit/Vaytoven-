# Vaytoven Rentals — Architecture

How the system is composed, where it runs, and *why* the non-obvious decisions were made. Update this in the same PR as any change that affects deployment, data flow, or the four foundational decisions below.

---

## The four foundational decisions

These are locked. Changing them is a multi-week migration; do not change without a written deprecation plan.

### 1. MySQL — not Postgres

**Decision:** MySQL 8.4 on Laravel Cloud.

**Why:** The master implementation prompt assumes Postgres 15 + PostGIS. The platform we're hosted on (Laravel Cloud) uses MySQL clusters; switching would require re-provisioning four clusters, re-attaching schemas, re-credentialing, re-debugging ProxySQL grants, and rewriting every migration to Postgres syntax — weeks of work for a marginal benefit at MVP scale.

**Substitutes for Postgres-only patterns:**

| Postgres | MySQL substitute |
|---|---|
| PostGIS `geography` + `ST_DWithin` | MySQL spatial: `POINT SRID 4326`, `ST_Distance_Sphere`, bounding-box prefilter, `SPATIAL INDEX` |
| `EXCLUDE USING gist (property_id WITH =, daterange WITH &&)` | `SELECT ... FOR UPDATE` inside a transaction + `UNIQUE (property_id, check_in_date, check_out_date)` for cheap duplicate detection. Application code must lock the property row with `LOCK IN SHARE MODE` before checking overlap. |
| `JSONB` with `->>` operator | `JSON` column with `JSON_EXTRACT()`, `->>` works in 8.0+ |
| `ENUM` (preferred via lookup tables) | `ENUM(...)` columns — fine for small fixed sets; lookup tables for anything that may grow |
| Trigger raises `EXCEPTION` | Trigger raises `SIGNAL SQLSTATE '45000'` — same effect, different syntax |
| `TIMESTAMPTZ` | `TIMESTAMP(6)` — UTC-only, app handles tz conversion |
| Hash chain (`pgcrypto`) | `SHA2(...)` (256) function in MySQL — built-in |

**Local dev:** the existing `.env.example` says `DB_CONNECTION=pgsql` — stale. Update to `mysql`. Local devs run MySQL via Docker (Compose file ships in Phase 2).

### 2. Flat Laravel — not split into `backend/` `web/` `app/` `admin/`

**Decision:** the repo stays as a single Laravel project at the root.

**Why:** the master prompt describes a multi-folder structure (`backend/`, `web/`, `app/`, `admin/`). Splitting now requires moving every file, reconfiguring composer autoload, updating Laravel Cloud's repository root, breaking every import. Adapting the mental model to a flat layout costs nothing.

**Mapping:**

| Master prompt term | Repo location |
|---|---|
| `backend/` | the repo itself (Laravel app) |
| `web/` (marketing) | `resources/views/welcome.blade.php` (1214 LOC, brand-correct) + `public/` (static + JS SDKs) |
| `app/` (traveler/host) | `resources/views/*` (Blade) + Livewire components for interactivity |
| `admin/` (operations console) | `resources/views/admin/*` (Blade) + Livewire components |

If a future PM mandates a true SPA frontend, the migration is contained: swap Livewire for Inertia + React. The controller layer doesn't change.

### 3. Blade + Livewire/Volt — not React

**Decision:** Blade for static + Livewire/Volt for interactive surfaces. No build framework added without explicit approval.

**Why:**
- Livewire + Volt are already required (`livewire/livewire ^3.6`, `livewire/volt ^1.7`).
- The marketing landing (`welcome.blade.php`) renders 1214 LOC of brand content with zero build step. That's the bar.
- The master prompt's React 18 single-file pattern (Babel-in-browser JSX) has terrible Lighthouse scores and locks future work into a custom build pipeline. Not adopting.
- Livewire covers all "interactive but not SPA" cases: booking modal, host dashboard, admin queues, support chat, Members enquiry funnel.

**When React might be reconsidered:** if mobile (Phase 2) needs to share render logic with web, switch to Inertia + React. Until then, no.

### 4. Auth — Breeze (web) + Sanctum (API)

**Decision:** Laravel Breeze for web sessions (already installed), Sanctum for API tokens (added in Phase 3).

**Why:**
- Breeze ships login/register/password reset out of the box; no need to invent.
- Sanctum is the standard Laravel choice for SPA + mobile API. Token-based, no cookies on third-party clients.
- We avoid Jetstream (heavier; brings teams + 2FA + API tokens we don't need yet).

---

## Topology

```
                     ┌──────────────────────────────────────────┐
                     │             Cloudflare (CDN)             │
                     └───────────────────┬──────────────────────┘
                                         │
                     ┌───────────────────┴──────────────────────┐
                     │   Laravel Cloud — us-east-2 (Ohio)       │
                     │                                          │
   ┌─────────────────┼──────────────────┬───────────────────────┼──────────────┐
   │                 │                  │                       │              │
   ▼                 ▼                  ▼                       ▼              ▼
v-app-dev      v-app-sandbox     v-app-production         v-app-dev's     external:
(main env)     (main env)        (main env)              MySQL cluster   - Stripe API
                                                         + Redis cache   - DocuSign
                                                                         - MaxMind
                                                                         - Anthropic
                                                                         - Meilisearch
```

- Three apps, each with one `main` env, each tied to a different branch in the `primeeventsource-bit/Vaytoven-` GitHub repo.
- Each app has its own dedicated MySQL cluster — credentials never shared. A leaked dev secret cannot reach prod data.
- Each app has its own Redis cache.
- Internal network is `*.us-east-2.db.laravel.cloud` (no `.public.` segment) — that's the only hostname ProxySQL will accept connections from inside the container fleet.

### Branches → environments

| Branch | App | URL |
|---|---|---|
| `development` | v-app-dev | https://v-app-dev-main-oyo1n9.laravel.cloud |
| `sandbox` | v-app-sandbox | https://v-app-sandbox-main-3m6clk.laravel.cloud |
| `production` | v-app-production | https://v-app-production-main-pkuean.laravel.cloud |

Push-to-deploy auto-fires on each branch. Merge `development → sandbox` only after dev is verified end-to-end. Merge `sandbox → production` only after sandbox is verified end-to-end.

### Database clusters

| Cluster | Tier | Storage | Used by |
|---|---|---|---|
| vaytoven-development | db-flex.m-1vcpu-512mb | 5 GB | v-app-dev |
| vaytoven-sandbox | db-flex.m-1vcpu-512mb | 5 GB | v-app-sandbox |
| vaytoven-production | db-pro.m-1vcpu-4gb | 20 GB | v-app-production |
| vaytoven__main | db-flex.m-1vcpu-2gb | 20 GB | (legacy — to be retired after Phase 1 verifies the new layout) |

---

## Background work

- **Queues:** Redis driver (matches Cache + Session). Horizon dashboard at `/admin/horizon` (admin-only).
- **Scheduler:** Laravel's built-in scheduler runs via the Laravel Cloud cron. Schedule lives in `app/Console/Kernel.php`.
- **Webhooks:** all incoming webhooks idempotency-checked via `<processor>_events` table with `event_id UNIQUE`. CSRF-excluded in `bootstrap/app.php`. HMAC-verified where the processor supports it (DocuSign already does this; pattern repeated for the 10 payment processors in Phase 5/6).

---

## Observability

- **Logs:** `LOG_CHANNEL=laravel-cloud-socket` in production with `LOG_STDERR_FORMATTER=Monolog\Formatter\JsonFormatter` — Laravel Cloud's log search is queryable by structured field (level, channel, datetime, context.*).
- **Health:** `/up` is Laravel's built-in shallow check (200 if app boots). `/health` is our deeper check — pings DB and Redis, returns 503 if either is down.
- **Login tracking:** `login_sessions` table records every authentication event with full geo/device enrichment. Powers anomaly detection and chargeback evidence (FR-10.7–10.9).
- **Tracking events:** `tracking_events` table records every meaningful user action — append-only with hash-chain tamper detection. Powers chargeback evidence bundles (FR-10.1–10.6).

---

## Security model

| Concern | Mitigation |
|---|---|
| Card data | Never touched by our app — Stripe Elements / SetupIntent client-side flow. The `payment_intents` table stores the Stripe-side ID, not card numbers. PCI scope = SAQ-A. |
| PII in logs | `LogProcessor` (Phase 8) masks emails, phones, IPs at log-write time. |
| Secrets in repo | Banned. Pre-commit hook (Phase 0) checks for AWS keys, Stripe keys, GitHub tokens, Anthropic keys, Slack webhooks. |
| SQL injection | Eloquent or named-param queries everywhere. Raw SQL only in migrations or stored procedures. |
| XSS | Blade auto-escapes; `{!! !!}` requires explicit reviewer comment justifying. |
| CSRF | Laravel default; webhooks CSRF-excluded but HMAC-verified. |
| Session fixation | Laravel rotates session ID on auth state change. |
| Rate limiting | Per-route via `throttle:N,M` middleware; chat-specific 30/IP/min and 120/user/min in Phase 7. |
| Admin role escalation | `EnsureAdmin` middleware checks `role` enum; `super_admin` is required to change role of another admin. |

---

## Trademark caveat

The name "Vaytoven" has not been cleared by counsel. Two phonetically close marks exist in the vacation-rental space:
- **VAYSTAYS** (USPTO Reg. #4693380, Class 36)
- **Vayk Holiday Homes** (UAE)

Counsel review is required before any branding spend or legal entity formation. The placeholder LICENSE in the repo carries this warning. Phase 13 (counsel review) blocks any user-facing rebrand.

---

## What this doc does NOT cover

- **API surface** — see the OpenAPI spec generated from `routes/api.php` (Phase 3 deliverable)
- **DB schema** — see `docs/schema.sql`
- **Functional requirements** — see `docs/SRS.md`
- **Phased plan** — see `docs/roadmap.md`
- **DocuSign-specific setup** — see `docs/docusign-setup.md`
- **Cloud safety / first-deploy runbook** — see `docs/01-step1-cloud-safety.md`, `docs/02-laravel-cloud-deployment.md`
