# Vaytoven Rentals

**Find your place anywhere.** Vacation rental marketplace platform.

This monorepo contains the full Vaytoven Rentals product: the consumer marketing site, the React web app, the operations console, and the Laravel backend that powers all three.

> **Status:** Pre-launch. Not yet running in production. Schema, SRS, and roadmap are versioned in `docs/`.
>
> **Trademark note:** Brand clearance has not yet been completed. Two phonetically-close marks exist in the vacation-rental space (VAYSTAYS — USPTO #4693380, Class 36; Vayk Holiday Homes — Dubai). Get attorney sign-off before any branding spend or legal entity formation.

---

## Repository layout

```
.
├── backend/         Laravel 11 monolith — REST API + admin endpoints (PHP 8.3, Postgres 15+)
├── web/             Marketing landing page (single-file HTML, designed for static hosting)
├── app/             React web app prototype — search, booking, host dashboard
├── admin/           Operations console — Members enquiries queue + future moderation tools
├── docs/            SRS, architecture, schema, roadmap, pitch deck
├── scripts/         Repo-wide tooling (build helpers, smoke tests)
└── .github/         PR template, issue templates, CI workflows
```

Each top-level surface has its own `README.md` with run instructions specific to that surface. Start with the surface you're working on; come back here for cross-cutting concerns.

---

## Quick start

### 1. Backend (Laravel API)

```bash
cd backend
cp .env.example .env
composer install
php artisan key:generate
# configure DATABASE_URL in .env (Postgres 15+ recommended; PostGIS optional for geo queries)
php artisan migrate
php artisan db:seed
php artisan serve            # http://localhost:8000
```

The API is versioned under `/api/v1`. See `backend/README.md` for the full route inventory.

### 2. Marketing site (web/)

The landing page is a single self-contained HTML file with no build step.

```bash
# Just open it
open web/index.html
# Or serve it
cd web && python3 -m http.server 8080
```

For real deployment, drop `web/index.html` on any static host (Cloudflare Pages, Netlify, S3+CloudFront).

### 3. Web app prototype (app/)

```bash
cd app
npm install --save-dev @babel/core @babel/preset-env @babel/preset-react
node build.js                # produces dist/index.html — single self-contained file
open dist/index.html
```

The source is in `app/index.html` (JSX in a `<script type="text/babel">` block). The build script inlines React UMD bundles and compiles JSX so the artifact has zero runtime dependencies — useful for quick demos and CI snapshots.

### 4. Admin console (admin/)

Same build pattern as `app/`. By default the admin UI uses an in-memory mock backend so it runs entirely client-side. To point it at the real Laravel API, see "Switching to the production API" in `admin/README.md`.

```bash
cd admin
node build.js
open dist/index.html
```

---

## Three audiences, three flows

Vaytoven serves three distinct user types:

1. **Travelers** — search, book, leave reviews. Standard guest flow.
2. **Property hosts** — list their own homes, manage bookings, receive payouts. Self-serve onboarding.
3. **Vacation property members** — owners in points-based programs (Marriott Vacation Club, Hilton Grand Vacations, Disney Vacation Club, RCI Points, etc.) who want to rent unused inventory through Vaytoven's Managed Listing Program. **Lead-qualified, sales-assisted onboarding** — not self-serve. Members submit an enquiry, a specialist contacts them, and qualified members have their unused weeks converted into managed listings.

The Managed Listing Program is implemented as a parallel inventory channel. See `docs/SRS.md` §3.9 (FR-9.1 through FR-9.11) for the full specification.

### Language convention (FR-9.8)

Public-facing copy NEVER uses the legal industry term "timeshare." Instead use:
- "Vacation property" / "vacation properties"
- "Vacation club" / "club membership"
- "Points-based ownership"
- "Member" (for the owner)

This applies to landing page, app, modal copy, admin UI labels, error messages, and analytics events. Legal disclosures (member agreement, terms of service) are a Phase-2 deliverable and are the only place where the precise legal term may appear, and only after counsel review.

---

## Architecture at a glance

```
                         ┌──────────────┐
                         │  Cloudflare  │  CDN + WAF + edge rate-limit
                         └──────┬───────┘
                                │
              ┌─────────────────┼─────────────────┐
              │                 │                 │
       ┌──────▼─────┐    ┌──────▼─────┐    ┌─────▼──────┐
       │   web/     │    │   app/     │    │  admin/    │
       │ (static)   │    │ (React)    │    │ (React)    │
       └──────┬─────┘    └──────┬─────┘    └─────┬──────┘
              │                 │                 │
              └─────────────────┼─────────────────┘
                                │
                       ┌────────▼────────┐
                       │  Laravel API    │  /api/v1/*
                       │  (backend/)     │  Sanctum auth
                       └────────┬────────┘
                                │
              ┌─────────────────┼─────────────────┐
              │                 │                 │
       ┌──────▼─────┐    ┌──────▼─────┐    ┌─────▼──────┐
       │ Postgres   │    │   Redis    │    │  Stripe    │
       │ + PostGIS  │    │  sessions  │    │  Connect   │
       └────────────┘    └────────────┘    └────────────┘
```

Full architecture document: [`docs/architecture.md`](docs/architecture.md).

---

## Key design decisions

- **Money is stored as integer cents.** Never floats. Never decimals. See `docs/schema.sql` and any `*_cents` column.
- **Bookings cannot overlap.** Enforced at the database level via Postgres `EXCLUDE` constraint. Application logic also checks, but the DB is the ground truth.
- **Stripe webhooks are idempotent.** Every event ID is recorded; replays are no-ops. See `app/Http/Controllers/StripeWebhookController.php`.
- **Admin actions are auditable.** Every state-changing admin call writes to `admin_audit_logs` with before/after JSON.
- **Confirmation codes** use the format `VYT-` + 6 uppercase alphanumeric chars (e.g. `VYT-K3M9P2`). Used in URLs, emails, and customer support — short enough to read aloud, long enough to make collision negligible.

---

## Testing

```bash
cd backend
./vendor/bin/phpunit                    # full feature + unit suite
./vendor/bin/phpunit --filter Members   # just the Members-enquiry tests
```

The Members-enquiry test suite covers: valid submission, rejected-without-consent, unknown program, short property name, disposable-email spam scoring, no-PII echo, admin list excluding flagged by default, include_flagged toggle, non-admin forbidden, admin self-assign, status transitions, reject-requires-reason, rate limiting (10/IP/hour).

---

## Deployment

Not yet automated. Roadmap target: ECS Fargate behind ALB, Postgres on RDS with read replica, Redis on ElastiCache, Horizon-managed queues. See [`docs/roadmap.md`](docs/roadmap.md) for the full 22-week build plan.

---

## Documentation

- **[Software Requirements Specification](docs/SRS.md)** — what the system must do, with FR-IDs
- **[Architecture](docs/architecture.md)** — how it's built and why
- **[Database schema](docs/schema.sql)** — 33 tables, fully documented
- **[Roadmap](docs/roadmap.md)** — 22-week build plan with milestones
- **[Landing page spec](docs/landing-page-spec.md)** — content + design decisions for `web/`
- **[Pitch deck](docs/pitch-deck.pptx)** — investor and partner narrative

---

## Contributing

Internal team only at this stage. See [`CONTRIBUTING.md`](CONTRIBUTING.md) for branch naming, PR process, and code style.

---

## License

Proprietary. All rights reserved. See [`LICENSE`](LICENSE).
