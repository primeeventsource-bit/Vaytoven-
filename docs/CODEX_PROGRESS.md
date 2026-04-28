# Codex Progress Log

Last updated: 2026-04-28

This file records continuation progress from the Laravel build workspace when the implementation repository is local and the specification repository is on GitHub.

## 2026-04-28 - Foundation Model Cleanup

Local Laravel app path:

```text
/Users/christiandior/Documents/Codex/2026-04-27/master-prompt-vaytoven-rentals-laravel-blade/vaytoven
```

New local app commit:

```text
326f4e0 refactor(models): add foundation relationships
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

Verification run:

```bash
php artisan test --filter SmokeTest
find app/Models app/Services tests database -type f -name '*.php' -print0 | xargs -0 -n1 php -l
```

Result:

```text
Smoke test passes.
PHP syntax checks pass.
```

Still blocked by environment:

- npm DNS still fails with `getaddrinfo ENOTFOUND registry.npmjs.org`.
- PostgreSQL startup is still blocked by sandbox shared-memory restrictions.
- PostGIS is still not available in the local PostgreSQL extension directory.
- Full migrations and database-backed tests cannot run until PostgreSQL 15+ with PostGIS is available outside the sandbox.

Next recommended task:

1. Get npm working and run `npm install && npm run build`.
2. Get PostgreSQL 15+ with PostGIS running.
3. Run `php artisan migrate:fresh --seed`.
4. Fix any executable migration issues while preserving `docs/schema.sql` intent.
5. Run `php artisan test --filter SmokeTest`.
6. Commit Phase 1 only when migrations and smoke test both pass.
