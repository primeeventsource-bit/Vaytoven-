# AGENTS.md — Rulebook for AI agents and contributors working on Vaytoven

Read this before generating or modifying code. The master implementation prompt is the *what*; this is the *how*. Source of truth for how work gets done. If a rule conflicts with `docs/SRS.md`, raise it as a question — don't just pick one.

---

## 1. Stack — non-negotiable

- **Backend:** PHP 8.3+, Laravel 11, **MySQL 8.4** (NOT Postgres — see `docs/architecture.md` for why), Redis, Sanctum (web sessions via Breeze, API tokens via Sanctum)
- **Frontend:** Blade + Livewire/Volt for interactive surfaces. Static HTML for the marketing landing page. **Do not introduce Vite, Next, CRA, React, or any build framework** without explicit approval.
- **Mobile:** deferred to Phase 2; native iOS (Swift/SwiftUI) and Android (Kotlin/Compose) consuming `/api/v1`
- **Hosting:** Laravel Cloud (us-east-2), three apps (`v-app-dev`, `v-app-sandbox`, `v-app-production`), each with a dedicated MySQL cluster + Redis

## 2. Hard rules — never violate

- **Money is integer cents.** `BIGINT` in DB, `int` in PHP, `number` (cents) in JS. Never float, never decimal, never bcmath.
- **Bookings cannot overlap.** MySQL substitute for Postgres `EXCLUDE`: `SELECT ... FOR UPDATE` inside a transaction + a unique check on `(property_id, check_in_date, check_out_date)`. The DB-level guarantee is defense-in-depth.
- **Webhooks must be idempotent.** Every processor's webhook records its `event_id` in a per-processor events table; replays are no-ops.
- **Admin actions write to `admin_audit_logs`** via `AdminAuditLogService::log(...)`.
- **`tracking_events` is append-only.** MySQL trigger blocks UPDATE and DELETE. Never write code that mutates a tracking event.
- **Confirmation codes:** `VYT-` + 6 uppercase alphanumeric. Server-generated.
- **The T-word is banned in user-facing copy** (FR-9.8). Use "vacation property," "vacation club," "points-based ownership," or "member."
- **Migrations are forward-only.** Never modify a shipped migration. Add a new one to fix.
- **No new state libraries, build frameworks, or CSS frameworks** without explicit approval.
- **No hardcoded secrets, no PII in logs, no card data in tracking payloads.**
- **Production deploys go through `development` → `sandbox` → `production` branches** — never push directly to `production`.

## 3. Documentation hierarchy

Read in this order when planning:

1. `docs/SRS.md` — what the system must do (FR-X.Y IDs)
2. `docs/schema.sql` — database design source of truth
3. `docs/architecture.md` — service topology, deployment, key decisions
4. `docs/roadmap.md` — phased implementation plan
5. Surface-specific READMEs

When SRS and code disagree, SRS wins. Update both in the same PR.

## 4. How to take a task

1. Read the FR-IDs in `docs/SRS.md` for the area you're working on
2. Read the analogous existing feature. The Members enquiry is the most polished end-to-end example; mirror its structure when adding new features.
3. **Plan before writing.** For tasks spanning more than ~3 files, write a short plan and get sign-off before generating code.
4. **Test first** for backend logic. Tests are not optional.
5. **Update the docs in the same PR.** SRS for new behavior, schema.sql for new columns, roadmap.md for status changes.
6. Self-review the diff. No `console.log`, `dd()`, `var_dump()`, commented-out code, or live API keys.

## 5. Branch and deploy policy

- All work lands on `development` first, deploys auto via push-to-deploy to https://v-app-dev-main-oyo1n9.laravel.cloud
- Verify end-to-end on dev before merging to `sandbox` (auto-deploys to https://v-app-sandbox-main-3m6clk.laravel.cloud)
- Verify end-to-end on sandbox before merging to `production` (auto-deploys to https://v-app-production-main-pkuean.laravel.cloud)
- Each environment has its own dedicated MySQL cluster and Redis cache — credentials are not shared
- Never bundle "build new + retire old" into a single commit. Verification is its own step.

## 6. Testing

- Unit + feature tests in `tests/Unit/` and `tests/Feature/`
- Use `RefreshDatabase` trait for feature tests
- Use `WithFaker` trait for fixtures
- Tests run against in-memory SQLite (per `phpunit.xml`) — write portable SQL where possible; tag MySQL-only tests with `@requires` where unavoidable
- CI must be green before merge

## 7. Coding conventions

- PSR-12 enforced by `laravel/pint` (run before commit)
- Form requests for all writes (`StoreFooRequest`, `UpdateFooRequest`)
- API resources for all reads (`FooResource`)
- Service classes for business logic (`app/Services/<Domain>/<Action>Service.php`)
- Controllers stay thin — delegate to services
- Eloquent over raw SQL except where a perf measurement says otherwise
- One model per file, one migration per concept
- Named routes always (`->name('foo.bar')`); reference via `route('foo.bar')` not URL strings
- Translatable strings via `__('message.key')` — even if i18n isn't done yet, don't hardcode user-facing English

## 8. AI agent specifics

- **Don't run destructive commands** without explicit user approval. Destructive includes: deletions, force pushes, `--no-verify`, `git reset --hard`, dropping migrations/tables, replacing env vars (`--action replace`).
- **Use `cloud db-cluster:get --show-sensitive`** to fetch DB credentials at runtime — never store plaintext in memory or repo files.
- **The internal Laravel Cloud DB hostname** (no `.public.` segment) is what works from inside containers. The public hostname is rejected by ProxySQL. See `docs/architecture.md`.
- **Schema attach via Laravel Cloud dashboard**, not CLI — `cloud env:update --database-id` and `cloud db:create` skip ProxySQL grant provisioning.
- **Master Admin** on dev: `primeeventsource@gmail.com` / role `super_admin` (set in Phase 1).
