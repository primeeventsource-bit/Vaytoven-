# Vaytoven Backend

Laravel 11 API skeleton for the Vaytoven Rentals platform.

This is a **scaffold** — it gives you the project structure, key models, migrations, controllers, services, API routes, validation, and a Stripe webhook handler that's wired the right way. You'll still need to add authentication flows, fill in business logic edge cases, write tests, and harden it for production. But the bones are here, and they follow the patterns the SRS describes (`02_SRS.docx`) against the schema (`01_schema.sql`).

## What's in the box

```
backend/
├── app/
│   ├── Models/                 # Eloquent models (User, Property, Booking, etc.)
│   ├── Http/
│   │   ├── Controllers/Api/    # Versioned API controllers
│   │   ├── Requests/           # Form request validators
│   │   ├── Resources/          # API response transformers
│   │   └── Middleware/         # IP/location logger, rate limit, etc.
│   ├── Services/               # Business logic (Pricing, Booking, Stripe)
│   ├── Jobs/                   # Queued work (payouts, notifications)
│   └── Events/Listeners/       # Domain events (BookingConfirmed → notify)
├── database/
│   ├── migrations/             # Schema migrations matching 01_schema.sql
│   ├── factories/              # Model factories for testing
│   └── seeders/                # Initial data (roles, amenities, demo users)
├── routes/
│   ├── api.php                 # /api/v1/* endpoints
│   └── web.php                 # Stripe webhook + health check
├── config/                     # auth, services, sanctum, etc.
├── tests/                      # Feature + Unit tests
└── composer.json
```

## Quick start (developer)

```bash
# 1. Create a real Laravel project (this skeleton is illustrative — start by
#    laying it on top of a fresh `laravel new` install, or copying these
#    files into one).
composer create-project laravel/laravel vaytoven-api
cd vaytoven-api

# 2. Copy the files from this skeleton over the fresh install
#    (composer.json, app/, database/, routes/, config/services.php).

# 3. Install dependencies
composer install

# 4. Configure environment
cp .env.example .env
php artisan key:generate

# Edit .env:
#   DB_CONNECTION=pgsql
#   DB_HOST=127.0.0.1
#   DB_PORT=5432
#   DB_DATABASE=vaytoven
#   DB_USERNAME=vaytoven
#   DB_PASSWORD=...
#   STRIPE_KEY=pk_test_...
#   STRIPE_SECRET=sk_test_...
#   STRIPE_WEBHOOK_SECRET=whsec_...
#   MAXMIND_LICENSE_KEY=...

# 5. Run migrations + seeders
php artisan migrate --seed

# 6. Start the dev server
php artisan serve
# → http://127.0.0.1:8000

# 7. Queue worker (for emails, payouts, etc.)
php artisan queue:work redis
```

## Authentication

Uses **Laravel Sanctum** for SPA + mobile token auth.
- `POST /api/v1/auth/register` → creates user, returns token
- `POST /api/v1/auth/login` → token (subject to rate limit + login_activity log)
- All other endpoints require `Authorization: Bearer <token>`

## Stripe

Connect Express for hosts. Payment Intents for guests.
- Webhook receives at `POST /webhook/stripe` (no auth, signature verified)
- Idempotent event handling — safe to replay from Stripe dashboard
- Refunds and disputes flow through `App\Services\StripeService`

## Key services

| Service | Responsibility |
|---|---|
| `BookingService` | Date-range collision check, price calc snapshot, status transitions |
| `PricingService` | Nightly + cleaning + service fee + tax math |
| `PayoutService` | Schedule payout 24h after check-in, retry on Stripe failure |
| `StripeService` | All Stripe API calls, with proper error mapping |
| `LoginActivityService` | IP→location, suspicious-login detection |
| `NotificationService` | Multi-channel (email/SMS/push/in-app) dispatch |

## Tests

```bash
php artisan test
```

Critical paths covered:
- Booking date collision (no double-booking under concurrency)
- Pricing math (off-by-one cents, rounding edges)
- Cancellation refund calc (flexible/moderate/strict)
- Stripe webhook idempotency

## What this skeleton intentionally doesn't do

- **No email templates** — wire up Mailable classes against your transactional provider
- **No mobile push** — Twilio/OneSignal/APNs adapter is a stub
- **No admin panel UI** — admin endpoints exist; UI is a separate React/Inertia app
- **No PostGIS spatial queries in models** — the migration enables PostGIS but Eloquent geo helpers (e.g., `matanyadaev/laravel-eloquent-spatial`) need to be wired by you

## Production checklist (before launch)

- [ ] Replace all `TODO` markers in code (search the codebase)
- [ ] Run `php artisan config:cache && php artisan route:cache && php artisan view:cache`
- [ ] Set up Horizon for queue monitoring
- [ ] Configure Sentry/Datadog DSN in `config/logging.php`
- [ ] Penetration test before opening signup
- [ ] GDPR data export job tested with a real user dataset
- [ ] Stripe live keys + webhook signing secret rotated from the test ones
