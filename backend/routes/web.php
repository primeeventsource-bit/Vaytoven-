<?php

use App\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

/*
| Web routes — these run with web middleware (sessions, cookies).
| The Stripe webhook is bound here NOT in api.php because:
|   - It needs to skip Sanctum auth (Stripe doesn't send our tokens)
|   - It needs to skip CSRF (Stripe sends raw POST without our token)
|   - VerifyCsrfToken middleware is excluded for /webhook/* in app/Http/Middleware/VerifyCsrfToken.php
*/

Route::get('/', fn () => response()->json(['service' => 'Vaytoven API']));

Route::post('/webhook/stripe', [StripeWebhookController::class, 'handle'])
    ->name('webhook.stripe');
