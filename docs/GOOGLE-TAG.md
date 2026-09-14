# Google Ads base tag — 2026-09-14

The requested destination is `AW-18384124631`. `public/vyt-google-tag.js` initializes `dataLayer`, calls `gtag('js', new Date())` and `gtag('config', 'AW-18384124631')`, and asynchronously loads Google's library. It preserves an existing queue/function and guards against duplicate initialization.

Browser page layouts include `partials.google-tag` in their heads. Email/PDF templates do not include browser tracking. The mobile build copies the same script into `mobile/www`, the browser preview, and the synced Android/iOS bundles. Rebuild and reinstall apps to receive updates; previously downloaded binaries do not update themselves.

The enforced website CSP allows the documented Google Ads script/pixel/connection endpoints and Google Tag Manager helper frame. The mobile CSP permits the same script hosts without enabling inline script or eval. The website includes `www.google.ae` for the regional pixel observed during UAE verification, as well as Google's main domains. Additional country-domain pixels may require explicit CSP entries when testing traffic in other regions; CSP cannot express a wildcard for a top-level domain.

## Verification

- 16 PHP Google-tag/security-header tests passed (59 assertions), covering single inclusion on six routes and preservation of existing CSP protections.
- Six JavaScript unit tests passed, including duplicate initialization and reuse of an existing gtag function.
- Real Chrome checks on `/`, `/properties`, `/login`, and `/app/#explore`: one loader, one config command, zero CSP violations; Google script and Ads measurement/pixel endpoints returned HTTP 200/204.
- Rebuilt Android debug APK; installed Android emulator WebView had the correct tag initialized once, zero CSP violations, ten property cards, and HTTP 200 from the tag library and Ads pixels for destination `18384124631`.
- Rebuilt iOS Simulator app; iPhone 17 Pro simulator network logs show `https://www.google.com/rmkt/collect/18384124631/` receiving HTTP 200. This is runtime request evidence, not verification of Google Ads account reports or native app attribution.

Local evidence (ignored): `.local/google-tag-browser-results.json`, `.local/android-google-tag-results.json`, `.local/ios-google-tag-network.log`, `.local/android-google-tag-build.log`, and `.local/ios-google-tag-build.log`.

Run the regression checks with:

```bash
php vendor/bin/phpunit --filter 'GoogleTagTest|SecurityHeadersTest'
npm --prefix mobile test
```

Build instructions and local artifact paths remain in [LOCAL-AND-MOBILE.md](LOCAL-AND-MOBILE.md). Google receives real test traffic from the local verification; no synthetic purchase/lead conversion events were sent.

## Scope and deployment

This installs the supplied **base web tag**, including inside the hybrid WebViews. No conversion label, purchase value, signup event, enhanced-conversion user data, advertising-ID collection, or Firebase native SDK was added. Existing consent mechanisms are not overridden; this change does not implement a new consent-management UI.

The base tag loading in a WebView does not establish app-install attribution. Native install/in-app conversion measurement needs an appropriate native integration (such as Firebase linked to Google Ads) and configuration for each registered app. No Firebase configuration was provided. Google Ads reporting/attribution and physical-device coverage have not been certified by these local checks.

Publish through development and the repository's environment promotion process. After deploying to the real website origin, validate with Google Tag Assistant and check the destination's tag diagnostics in Google Ads. Uploading this source branch alone does not change a running production site.

References: [Google tag installation](https://developers.google.com/tag-platform/gtagjs), [Google Ads CSP requirements](https://developers.google.com/tag-platform/security/guides/csp#google_ads), [Google's hybrid WebView/Firebase integration](https://codelabs.developers.google.com/codelabs/ga4f-event-tracking-webview).
