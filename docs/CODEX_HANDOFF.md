# Codex Handoff - Laravel Build

Last updated: 2026-04-28

This handoff captures the current implementation state for continuing Vaytoven Rentals in Codex, Cursor, Claude Code, or VS Code.

## Project Locations

Specification repository:

```text
/Users/christiandior/Documents/Codex/2026-04-27/Vaytoven-
```

Laravel application workspace:

```text
/Users/christiandior/Documents/Codex/2026-04-27/master-prompt-vaytoven-rentals-laravel-blade/vaytoven
```

Production domain:

```text
https://www.vaytoven.com
```

The domain is hosted through Network Solutions.

## Read First

Before coding, read these files end to end:

- `docs/SRS.md`
- `docs/schema.sql`
- `docs/architecture.md`
- this file

The SRS and schema are authoritative. Do not invent requirements that contradict them.

## Current Laravel State

The Laravel app has been scaffolded as a Laravel 11 monolith using Blade and Livewire. There is no React, Vue, Inertia, Filament, or separate API frontend.

Installed foundation packages:

- Laravel 11
- Laravel Breeze with the Livewire stack
- Livewire 3
- Laravel Horizon
- Stripe PHP SDK
- Intervention Image
- Spatie Laravel Permission

Testing note:

- Pest was attempted but conflicted with Laravel 11's pinned PHPUnit version in this workspace.
- PHPUnit is currently used. The master prompt allows PHPUnit.

Current local app commits:

```text
81a1dea feat(foundation): scaffold Laravel app
17f6694 test(foundation): tolerate missing vite build
```

## Approved Schema Decisions

These decisions were made while translating the authoritative schema into Laravel migrations:

- `members_enquiries.assigned_to` should be a `BIGINT` foreign key to `users(id)`, not UUID.
- `members_enquiries.converted_property_id` should be a `BIGINT` foreign key to `properties(id)`, not UUID.
- `properties.listing_source` was added for managed listings.
  - Default: `self_listed`
  - Later managed member conversion: `managed`
- Use the schema/SRS roles first:
  - `guest`
  - `host`
  - `admin`
  - `super_admin`
- Add member-specialist style permissions later rather than changing the base role model too early.

## Chargeback Tracking Decision

Travel Enterprises and Vaytoven are separate sister-company platforms. Do not copy, ingest, or commit Travel Enterprises PII into Vaytoven or this repository.

Vaytoven needs its own backend tracking for chargeback evidence and operational review.

Tracking requirements added to the Laravel build:

- Login IP logs
- PPC attribution
- Website and app clicks
- Page views
- Property views
- Booking and payment evidence events
- Chargeback evidence bundles

The tracking design is per-platform, append-only, and hash-chained.

Added tables in the Laravel workspace:

- `tracking_events`
- `chargeback_disputes`
- `ppc_visitors`

Added service:

- `App\Services\TrackingService`

Evidence workflow should be processor-agnostic. Known processors to support later:

- Stripe
- Authorize.Net
- NMI
- Nuvei
- Merchant E
- Payment Cloud
- EMS
- Nexio
- Netevia
- Kurv

Important scope note: login and engagement evidence helps when a customer used the service and disputed anyway. It does not, by itself, prove delivery for a promised future service that did not happen.

## User-Facing Language Rule

Do not use the word `timeshare` in user-facing strings.

Use:

- vacation property
- vacation club
- points-based ownership
- member

The restricted term may appear only in internal legal-only comments or private documentation where explicitly marked.

## Local Toolchain

A project-local toolchain was installed in:

```text
/Users/christiandior/Documents/Codex/2026-04-27/master-prompt-vaytoven-rentals-laravel-blade/.toolchain
```

Activate it from the Laravel app directory:

```bash
. ../.toolchain/env.sh
```

Verified versions in this workspace:

```text
PHP 8.3.30
Composer 2.9.7
npm 11.12.1
PostgreSQL CLI 15.17
```

The helper sets workspace-local Composer and npm cache/log paths.

## Known Environment Blockers

These blockers were observed in Codex Desktop. They are environment issues, not Laravel code issues.

### npm DNS

`npm ping` failed with:

```text
getaddrinfo ENOTFOUND registry.npmjs.org
```

Fix DNS, VPN, firewall, or network access before running:

```bash
npm install
npm run build
```

### PostgreSQL Startup

The local PostgreSQL server failed during `initdb` with:

```text
could not create shared memory segment: Operation not permitted
```

This happened before Laravel could run migrations. Use a normal Terminal environment, Postgres.app, Docker, or another PostgreSQL 15+ install that can start outside the Codex sandbox.

### PostGIS

PostGIS was not present in the local PostgreSQL extension directory. The schema requires PostGIS. Do not downgrade geography columns to plain strings or decimals.

Install PostgreSQL with PostGIS support before running migrations.

### Local HTTP Server

`php artisan serve` failed in Codex Desktop because the sandbox blocked listening on local ports.

It should be run in normal Terminal or VS Code:

```bash
php artisan serve
```

Then open:

```text
http://127.0.0.1:8000
```

## Setup Commands

From the Laravel application directory:

```bash
cd /Users/christiandior/Documents/Codex/2026-04-27/master-prompt-vaytoven-rentals-laravel-blade/vaytoven
. ../.toolchain/env.sh
composer install
npm install
npm run build
createdb vaytoven
createdb vaytoven_test
php artisan migrate:fresh --seed
php artisan test
php artisan serve
```

If `createdb` or migrations fail, install or start PostgreSQL 15+ with PostGIS first. On macOS, Postgres.app with PostGIS is the simplest path.

## Current Verification

Passing:

```bash
php artisan test --filter SmokeTest
```

Also passing:

```bash
find app database -type f -name '*.php' -print0 | xargs -0 -n1 php -l
```

Blocked until database works:

```bash
php artisan migrate:fresh --seed
php artisan test
```

Current auth/profile test failures are database connection failures, not Vite failures.

## Continue In VS Code Prompt

Paste this into Codex in VS Code:

```text
You are working on Vaytoven Rentals, a Laravel 11 + Blade + Livewire monolith.

Project path:
`/Users/christiandior/Documents/Codex/2026-04-27/master-prompt-vaytoven-rentals-laravel-blade/vaytoven`

Authoritative spec repo:
`/Users/christiandior/Documents/Codex/2026-04-27/Vaytoven-`

Before coding, read:
- `../Vaytoven-/docs/SRS.md`
- `../Vaytoven-/docs/schema.sql`
- `../Vaytoven-/docs/architecture.md`
- `../Vaytoven-/docs/CODEX_HANDOFF.md`
- `README.md`

Current state:
- Laravel 11 app scaffold exists.
- Breeze Livewire auth scaffold is installed.
- Livewire 3, Horizon, Stripe PHP SDK, Intervention Image, and Spatie Permission are installed.
- Schema migrations were generated from `docs/schema.sql`.
- Extra tracking/chargeback tables were added:
  - `tracking_events`
  - `chargeback_disputes`
  - `ppc_visitors`
- `TrackingService` exists for append-only chargeback/evidence tracking.
- Vaytoven domain is `https://www.vaytoven.com`, hosted through Network Solutions.
- Current commits:
  - `81a1dea feat(foundation): scaffold Laravel app`
  - `17f6694 test(foundation): tolerate missing vite build`

Important product decisions:
- Vaytoven and Travel Enterprises are separate sister-company platforms.
- Do not copy or commit Travel Enterprises PII.
- Vaytoven needs its own backend tracking for login IPs, PPC clicks, page/app clicks, property views, and chargeback evidence.
- Tracking should be per-platform, append-only, and hash-chained.
- Payment processors to support in evidence workflows later:
  - Stripe
  - Authorize.Net
  - NMI
  - Nuvei
  - Merchant E
  - Payment Cloud
  - EMS
  - Nexio
  - Netevia
  - Kurv
- User-facing copy must not use the word “timeshare”; use “vacation property,” “vacation club,” “points-based ownership,” or “member.”

Known blockers from Codex desktop:
- npm could not reach `registry.npmjs.org` due DNS `ENOTFOUND`.
- PostgreSQL server startup was blocked by sandbox shared-memory restrictions.
- PostGIS was not available in the local PostgreSQL extension directory.
- `php artisan serve` was blocked in the sandbox, but should work in normal Terminal/VS Code.
- Smoke test passes.
- Full tests require working PostgreSQL/PostGIS.

Local toolchain helper:
From the project directory run:
`. ../.toolchain/env.sh`

Then verify:
```bash
php --version
composer --version
npm --version
psql --version
```

To get the app working locally:
```bash
cd /Users/christiandior/Documents/Codex/2026-04-27/master-prompt-vaytoven-rentals-laravel-blade/vaytoven
. ../.toolchain/env.sh
npm install
npm run build
createdb vaytoven
createdb vaytoven_test
php artisan migrate:fresh --seed
php artisan test
php artisan serve
```

If `createdb` or migrations fail, install/start PostgreSQL 15+ with PostGIS first. Postgres.app is the easiest macOS option.

Your first task:
1. Inspect the current repo status.
2. Confirm npm install/build works.
3. Confirm PostgreSQL/PostGIS works.
4. Run `php artisan migrate:fresh --seed`.
5. Run `php artisan test --filter SmokeTest`.
6. If migrations fail, fix only migration/schema issues that contradict executable Postgres behavior while preserving the SRS/schema intent.
7. Do not move to Phase 2 until Phase 1 migrations and smoke test pass.
```

## Next Engineering Task

Do not start Phase 2 yet.

First clear Phase 1:

1. Get npm working and build frontend assets.
2. Get PostgreSQL 15+ with PostGIS working.
3. Run `php artisan migrate:fresh --seed`.
4. Fix migration issues while preserving the SRS/schema.
5. Run the smoke test.
6. Commit Phase 1 only when migrations and smoke test both pass.
