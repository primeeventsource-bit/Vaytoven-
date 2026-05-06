# Copilot inline instructions for Vaytoven

Full rulebook in `AGENTS.md` at the repo root. Read that before suggesting code in any new area.

## Stack

Laravel 11, PHP 8.3+, **MySQL 8.4** (not Postgres), Redis, Blade + Livewire/Volt, Sanctum, Laravel Cloud hosting.

## Hard rules — never break

- **Money is integer cents.** BIGINT / int / number (cents). Never float, decimal, or bcmath.
- **No overlapping bookings.** Pattern: `SELECT ... FOR UPDATE` in a transaction + unique key on `(property_id, check_in_date, check_out_date)`.
- **Idempotent webhooks.** Record `event_id` in a per-processor events table; replays must be no-ops.
- **`tracking_events` is append-only.** MySQL trigger blocks UPDATE/DELETE. Never write code that mutates a tracking event.
- **Admin actions are audited** via `AdminAuditLogService::log(...)`.
- **Confirmation codes:** `VYT-` + 6 uppercase alphanumeric. Server-generated.
- **The T-word ("timeshare") is banned** in user-facing copy. Use "vacation property", "vacation club", "points-based ownership", "member".
- **Migrations are forward-only.** Never modify a shipped migration.
- **No new build frameworks** (Vite, Next, React, CRA) without explicit approval. Use Blade + Livewire/Volt.
- **No hardcoded secrets, no PII in logs, no card data in tracking payloads.**

## Conventions

- PSR-12 via `laravel/pint`.
- Form requests (`StoreFooRequest`) for writes, API resources (`FooResource`) for reads.
- Business logic in `app/Services/<Domain>/<Action>Service.php`.
- Controllers stay thin — delegate to services.
- Named routes everywhere (`->name('foo.bar')`).
- Eloquent over raw SQL.
- Translatable strings via `__('key')` even before i18n.
- Tests for business logic — use `RefreshDatabase` + `WithFaker`.

## When suggesting code

Prefer mirroring an existing pattern in the repo over inventing one. The Members enquiry feature (`MemberEnquiryController`, `MemberEnquiry` model, `MemberEnquiryTest`) is the most polished end-to-end example — match its structure for new features.
