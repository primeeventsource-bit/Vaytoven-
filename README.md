# Vaytoven Rentals

**Find your place anywhere.**

Vaytoven Rentals is now a Laravel 11 application at the repository root so Laravel Cloud can detect and deploy it directly.

## Laravel Cloud

Laravel Cloud should detect the app from these root files:

- `composer.json`
- `artisan`
- `public/index.php`
- `bootstrap/app.php`
- `routes/web.php`

Set production environment variables in Laravel Cloud, including `APP_KEY`, database credentials, mail settings, Stripe credentials, and Redis if enabled.

## Local Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
npm run build
php artisan migrate:fresh --seed
php artisan serve
```

The database must be PostgreSQL 15+ with PostGIS enabled.

## Documentation

The product specification remains in `docs/`:

- `docs/SRS.md`
- `docs/schema.sql`
- `docs/architecture.md`
- `docs/roadmap.md`

## Language Rule

User-facing copy must not use the legal industry term for vacation club ownership. Use vacation property, vacation club, points-based ownership, and member.
