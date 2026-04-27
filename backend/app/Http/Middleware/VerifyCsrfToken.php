<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * URIs excluded from CSRF verification.
     * Stripe sends webhooks without our CSRF token — that's by design.
     */
    protected $except = [
        'webhook/*',
    ];
}
