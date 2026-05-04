<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // DocuSign Connect webhooks don't carry a CSRF token; they're
        // authenticated by the X-DocuSign-Signature-* HMAC headers instead
        // (see App\Services\DocuSign\WebhookVerifier).
        $middleware->validateCsrfTokens(except: [
            'webhooks/docusign',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
