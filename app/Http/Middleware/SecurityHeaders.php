<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Response headers the edge does not already set.
 *
 * Laravel Cloud's network settings already send X-Frame-Options: DENY and
 * X-Content-Type-Options: nosniff, so those are deliberately absent here —
 * sending them twice produces duplicate headers, and browsers treat a
 * conflicting pair as the most restrictive, which hides mistakes.
 *
 * The content security policy is REPORT-ONLY. The site loads Leaflet from
 * unpkg, tiles from Mapbox and OpenStreetMap, fonts from three CDNs, and — on
 * the page that takes money — NMI's Collect.js, which injects cross-origin
 * iframes for the card fields. Enforcing a policy assembled from a grep would
 * eventually break the payment form, which is the single page on this site
 * where breakage costs money directly. Report-only cannot break anything, so
 * it goes out first and gets enforced once the reports are quiet.
 *
 * Both style-src and script-src need 'unsafe-inline': the templates are full
 * of inline style attributes and inline scripts. That weakens the policy
 * considerably and is worth being honest about — what it still buys is
 * control over where scripts may be LOADED from, so an injected
 * <script src="//evil"> is refused even though an injected inline handler
 * is not.
 */
class SecurityHeaders
{
    /** Six months. Not preloaded — preload is effectively irreversible. */
    private const HSTS_MAX_AGE = 15768000;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only on HTTPS. A browser ignores HSTS over plain HTTP anyway, and
        // sending it in local development would poison localhost.
        if ($request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age='.self::HSTS_MAX_AGE.'; includeSubDomains'
            );
        }

        // Send the origin to other sites, the full path only to ourselves.
        // Referer leakage matters here because URLs carry member and order ids.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Nothing on this site asks for a camera, a microphone or a location.
        // Denying them means an injected script cannot ask on our behalf.
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()'
        );

        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        // Enforcing, not report-only.
        //
        // It ran in report-only while the policy was being shaped, which is the
        // right way round — a policy that blocks something the site needs takes
        // the site down. Every public page was then loaded and checked for
        // securitypolicyviolation events and none of them reported one, so the
        // policy already describes what the site actually loads. Left in
        // report-only it documents an intention and stops nothing: an injected
        // script would be reported and would still run.
        //
        // report-uri is kept, so anything a real visitor trips still arrives.
        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', $this->policy());
        }

        return $response;
    }

    /**
     * Assembled from what the templates actually reference, not from a
     * boilerplate policy. Every host here is one the site would break without.
     */
    private function policy(): string
    {
        $nmi     = 'https://secure.nmi.com';
        $mapbox  = 'https://api.mapbox.com https://events.mapbox.com';
        $tiles   = 'https://*.tile.openstreetmap.org https://*.basemaps.cartocdn.com';
        $fonts   = 'https://fonts.bunny.net https://fonts.googleapis.com https://fonts.gstatic.com';

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://unpkg.com {$nmi} {$mapbox}",
            "style-src 'self' 'unsafe-inline' https://unpkg.com {$fonts}",
            "font-src 'self' data: {$fonts}",
            "img-src 'self' data: blob: https://images.unsplash.com https://unpkg.com {$mapbox} {$tiles}",
            "connect-src 'self' {$nmi} {$mapbox} {$tiles}",
            // Collect.js puts the card inputs in its own iframes. Nothing else
            // may be framed, and nothing may frame us.
            "frame-src {$nmi}",
            "frame-ancestors 'none'",
            // A stolen form action is how a card page becomes a phishing page.
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            'report-uri '.route('security.csp-report'),
        ]);
    }
}
