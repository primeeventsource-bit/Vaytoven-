<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // DocuSign Connect webhooks don't carry a CSRF token; they're
        // authenticated by the X-DocuSign-Signature-* HMAC headers instead
        // (see App\Services\DocuSign\WebhookVerifier). Stripe webhooks are
        // authenticated by the Stripe-Signature header (FR-4.3).
        $middleware->validateCsrfTokens(except: [
            'webhooks/docusign',
            'webhooks/stripe',
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'terms.current' => \App\Http\Middleware\EnsureCurrentTermsAccepted::class,
        ]);

        // Captures X-Vaytoven-Surface on every API request so login_sessions and
        // tracking_events record the correct surface (FR-10.7).
        $middleware->api(prepend: [
            \App\Http\Middleware\SetVaytovenSurface::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
