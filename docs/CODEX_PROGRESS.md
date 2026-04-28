# Codex Progress Log

Last updated: 2026-04-28

This file records continuation progress from the Laravel build workspace when the implementation repository is local and the specification repository is on GitHub.

## 2026-04-28 - Foundation Model Cleanup

Local Laravel app path:

```text
/Users/christiandior/Documents/Codex/2026-04-27/master-prompt-vaytoven-rentals-laravel-blade/vaytoven
```

Local app commits:

```text
326f4e0 refactor(models): add foundation relationships
abdd4f4 build(frontend): add npm lockfile
c1b4d6f fix(database): enable gist equality extension
```

What changed:

- Replaced broad generated model casts/scopes on core tables with schema-specific casts.
- Added Laravel relationships for the main Phase 1 model layer:
  - `User`
  - `Role`
  - `UserRole`
  - `Property`
  - `PropertyType`
  - `Amenity`
  - `PropertyImage`
  - `PropertyRule`
  - `PropertyAvailability`
  - `Booking`
  - `Payment`
  - `PaymentMethod`
  - `Payout`
  - `Review`
  - `Wishlist`
  - `WishlistItem`
  - `MessageThread`
  - `MessageThreadParticipant`
  - `Message`
  - `LoginActivity`
  - `MembersEnquiry`
  - `Dispute`
  - `ChargebackDispute`
- Marked tables without standard Laravel timestamps so Eloquent does not write missing `updated_at` columns.
- Fixed `ChargebackDispute::scopeEvidenceDue()` to use `evidence_due_at`, matching the migration.
- Removed accidental React/Inertia package changes from the local app and restored Blade/Livewire-only Vite config.
- Added `package-lock.json` after npm install succeeded from the available package set.
- Added `btree_gist` to the PostgreSQL extension migration because the booking no-overlap GiST exclusion constraint needs equality support for `property_id`.

Verification run:

```bash
php artisan test --filter SmokeTest
npm run build
find app/Models app/Services tests database -type f -name '*.php' -print0 | xargs -0 -n1 php -l
```

Result:

```text
Smoke test passes.
Vite build passes.
PHP syntax checks pass.
```

Still blocked by environment:

- `php artisan migrate:fresh --seed` fails because this sandbox cannot connect to PostgreSQL at `127.0.0.1:5432`.
- `php artisan serve` and `php -S` both fail in this sandbox with `Operation not permitted` when trying to listen on a local port.
- Installing PostGIS into the project-local Homebrew was attempted from cache, but Homebrew failed with `Operation not permitted - bind(2)` while creating an internal fork socket.
- Full migrations and database-backed tests cannot run until PostgreSQL 15+ with PostGIS is available outside the sandbox.

Next recommended task:

1. In a normal Mac Terminal or VS Code terminal, start PostgreSQL 15+ with PostGIS.
2. Run `createdb vaytoven && createdb vaytoven_test`.
3. Run `php artisan migrate:fresh --seed`.
4. Fix any executable migration issues while preserving `docs/schema.sql` intent.
5. Run `php artisan test --filter SmokeTest`.
6. Run `php artisan serve` and open `http://127.0.0.1:8000`.
7. Commit Phase 1 only when migrations and smoke test both pass.
