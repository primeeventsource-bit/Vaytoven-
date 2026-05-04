# Step 1 — Cloud-Safety Foundation

This pass makes Vaytoven safe to run as a stateless, multi-replica container
*before* we touch the Dockerfile or Azure infrastructure. Nothing here changes
deployment, CI, or hosting; the running production app keeps booting from the
existing Azure App Service workflow.

## What changed

### `.env.example`
- `SESSION_DRIVER`, `QUEUE_CONNECTION`, `CACHE_STORE` now default to `redis`.
- Added a comment block explaining why the file-based defaults are unsafe in
  containers (each replica has its own filesystem, so file sessions diverge
  and file-driver locks don't cross replicas).
- Redis connection variables were already present and remain unchanged.

### Persistent member-enquiry storage
- New migration: `database/migrations/2026_05_04_000001_create_members_enquiries_table.php`
- New model: `app/Models/MemberEnquiry.php`
- Columns mirror the existing form validation rules in `routes/web.php` so
  the request layer and the database agree on field lengths.
- `consented_at` is stored as a timestamp (not a boolean) so we keep evidence
  of *when* opt-in occurred — what most privacy regimes actually require.
- Source URL is captured from the `Referer` header, truncated to 500 chars.

### Route handler
- `routes/web.php` no longer logs to a file. The `Log::channel('single')`
  call and the `Illuminate\Support\Facades\Log` import are removed.
- The handler now writes a `MemberEnquiry` row and returns the same
  `{"ok": true}` JSON response. User-facing behavior is unchanged on success.

### Tests
- `phpunit.xml` and `tests/TestCase.php` added (Laravel 11 defaults, SQLite
  in-memory database).
- `tests/Feature/MemberEnquiryTest.php` covers the happy path, missing
  required fields, and missing consent.
- `tests/Feature/SmokeTest.php` covers `/` and `/up`. The contract show
  route is gated on auth and a real `Contract` row, so it's deferred to a
  later pass with proper auth scaffolding.

## Why we removed the file logger

File logging worked on a single-VM App Service host where the disk persisted
across requests. In a container:

1. **Disk is ephemeral.** A new revision starts with an empty `storage/logs/`,
   so any leads logged just before a deploy are lost.
2. **Disk is per-replica.** With ACA scaling to multiple replicas, half the
   leads land on container A's disk and half on container B's — neither host
   has the full picture, and you can't grep your way to a unified view.
3. **It's write-only.** Logs aren't queryable, exportable, or auditable.
   Sales follow-up needs a database row, not a log line.

The DB write also fails loud: if Postgres is down, the request returns a 500
and the user sees an error. That's correct. The previous behavior — silently
log to disk and return 200 — would have hidden a real outage from the user
and from monitoring.

## Follow-ups before containerization

These are *not* in this pass. They belong to the next phases of the rollout:

1. **Run the migrations against production.** The two existing DocuSign
   migrations (`2026_05_03_000001_create_contracts_table` and `_000002_`)
   have probably never executed; the new `members_enquiries` migration adds
   to that backlog. The container-deploy plan calls for a Container Apps
   migration job — until then, run `php artisan migrate --force` manually
   against the production Postgres before deploying any code that reads or
   writes these tables.

2. **Switch `LOG_CHANNEL` to `stderr`.** Today it's still `stack` → `single`
   (file). For containers, set `LOG_CHANNEL=stderr` in production env so
   logs stream to Container Apps' Log Analytics workspace. Not changed in
   this pass because it requires an env-var update on the live App Service
   first; we'll flip it as part of the container cutover.

3. **Decide on a fail-soft strategy for enquiry capture.** Today, if Postgres
   is down, the form returns 500 and the lead is lost. Options:
   - Accept the 500 (current behavior — simplest, fail-loud).
   - Queue to Redis and persist via worker (requires Redis to be more
     reliable than Postgres, which it generally is in Azure).
   - Dual-write to a fallback log channel as belt-and-braces.
   Recommend revisiting after Redis is provisioned in Phase 5.

4. **Build admin view + status workflow.** The `members_enquiries` table
   intentionally has no `status`, `assigned_to`, `contacted_at`, or `notes`
   columns — those belong to the admin enquiry CRUD that hasn't been built
   yet. Adding them now would be premature and would require schema churn
   when the admin view actually lands.

5. **Wire a `MemberEnquiryFactory`.** The model uses `HasFactory` but no
   factory file exists yet. Not needed for the current tests (they build
   payloads inline) but will be needed when seeding test data for the admin
   view work.

## How to validate locally

Once `composer install` has been run with dev dependencies (the production
deploy workflow currently runs without `--no-dev`, so the constraint is that
`vendor/bin/phpunit` is wired up):

```
php artisan migrate --database=sqlite --path=database/migrations
vendor/bin/phpunit
```

Or, with the real Postgres dev database:

```
php artisan migrate
vendor/bin/phpunit --testsuite=Feature
```

Three feature tests should pass: enquiry-persists, missing-fields-rejected,
consent-required.
