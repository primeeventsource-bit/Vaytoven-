<?php

use App\Http\Middleware\CheckMaintenanceMode;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureAdminOrMemberSpecialist;
use App\Http\Middleware\EnsureCurrentTermsAccepted;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\SetVaytovenSurface;
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
        // (see App\Services\DocuSign\WebhookVerifier). NMI webhooks are
        // authenticated by the Webhook-Signature header (FR-4.3).
        $middleware->validateCsrfTokens(except: [
            'webhooks/docusign',
            'webhooks/nmi',
        ]);

        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'admin.or.specialist' => EnsureAdminOrMemberSpecialist::class,
            'permission' => EnsurePermission::class,
            'terms.current' => EnsureCurrentTermsAccepted::class,
        ]);

        // Captures X-Vaytoven-Surface on every API request so login_sessions and
        // tracking_events record the correct surface (FR-10.7).
        $middleware->api(prepend: [
            SetVaytovenSurface::class,
        ]);

        // The forced password change applies to the API too. Without this an
        // account still holding a staff-issued password could skip the whole
        // flow by using a token instead of a browser.
        $middleware->api(append: [
            EnsurePasswordChanged::class,
        ]);

        // Operator-toggled maintenance mode (setting general.maintenance_mode).
        // Appended so it runs after session/auth resolve — admins pass through.
        //
        // EnsurePasswordChanged runs on every web route rather than on the
        // admin group alone: an account still holding a password somebody else
        // chose should not be able to act at all under that credential.
        $middleware->web(append: [
            CheckMaintenanceMode::class,
            EnsurePasswordChanged::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
