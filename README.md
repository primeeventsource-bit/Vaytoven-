# Vaytoven Rentals

**Find your place anywhere.**

Vaytoven Rentals is a Laravel 11 application at the repository root so Laravel Cloud can detect and deploy it directly.

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
php artisan serve
```

The full project specification remains in `docs/`.

## Language Rule

User-facing copy must use vacation property, vacation club, points-based ownership, and member.
