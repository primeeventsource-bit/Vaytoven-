<?php

namespace App\Services\GeoIp;

/**
 * GeoIP from Cloudflare's per-request headers. Free, zero-config when the
 * app is fronted by Cloudflare (Laravel Cloud is, by default).
 *
 * Strictly per-request: the cf-* headers describe only the current incoming
 * HTTP request. lookup() refuses to return data unless (a) we're inside an
 * HTTP request context and (b) the IP being looked up matches the request's
 * client IP. Otherwise we'd be pretending the current visitor's geo applies
 * to some unrelated IP — wrong and silently misleading.
 *
 * Headers we read (case-insensitive, varies by Cloudflare plan):
 *   cf-ipcountry   — ISO-3166 alpha-2 country code (free tier and up)
 *   cf-region      — administrative region name (Pro+)
 *   cf-ipcity      — city name (Pro+)
 *   cf-iplatitude  — decimal latitude  (Pro+)
 *   cf-iplongitude — decimal longitude (Pro+)
 *
 * If the upstream plan is free-tier, lat/lng/city won't populate and the
 * dashboard map won't gain pins from real visits — only the "Countries
 * reached" KPI does. Country-level geo is the guaranteed floor.
 *
 * Threat-intel fields (is_vpn / is_tor / is_datacenter / is_anonymous)
 * are always false from this provider — Cloudflare doesn't expose them
 * as plain request headers. For threat intel you need MaxMind GeoIP2
 * Anonymous IP, Shield, or an upstream API.
 */
class CloudflareHeaderGeoIpService implements GeoIpService
{
    public function lookup(?string $ipAddress): GeoIpResult
    {
        if (! $ipAddress) {
            return GeoIpResult::empty();
        }

        // Not inside an HTTP request (CLI, queue worker, artisan) → no headers.
        if (! app()->bound('request')) {
            return GeoIpResult::empty();
        }

        $request = app('request');
        if ($request->ip() !== $ipAddress) {
            // The cf-* headers describe the current request only.
            // Don't imply they describe a different IP.
            return GeoIpResult::empty();
        }

        $h = $request->headers;
        $country = $h->get('cf-ipcountry') ?: null;
        $lat = $h->get('cf-iplatitude');
        $lng = $h->get('cf-iplongitude');

        $latitude  = is_numeric($lat) ? (float) $lat : null;
        $longitude = is_numeric($lng) ? (float) $lng : null;

        // Free Cloudflare plans (and Laravel Cloud's, verified 2026-05-12)
        // only send cf-ipcountry. Fall back to the country's geographic
        // centroid so the dashboard map gets a pin from real traffic instead
        // of staying empty. Coarse, but useful — "you have visitors from
        // these countries" is the question the map actually answers.
        if ($latitude === null && $longitude === null && $country) {
            $centroid = CountryCentroids::for($country);
            if ($centroid !== null) {
                [$latitude, $longitude] = $centroid;
            }
        }

        return new GeoIpResult(
            country:   $country,
            region:    $h->get('cf-region') ?: null,
            city:      $h->get('cf-ipcity') ?: null,
            latitude:  $latitude,
            longitude: $longitude,
        );
    }
}
