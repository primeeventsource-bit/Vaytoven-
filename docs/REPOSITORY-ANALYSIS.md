# Vaytoven repository analysis — 2026-09-10

## Result

The current GitHub `main` was cloned separately and made live locally with PHP 8.4, MySQL 8.4, Redis, and seeded listings. Branded hybrid Android/iOS projects were added in `mobile/`; the Android debug APK and iOS simulator app compile and launch. The unsigned iPhone hardware target also compiles. New mobile endpoints share the website's accounts, saved properties, offer service, and audit trail.

Local URLs and operation instructions: [LOCAL-AND-MOBILE.md](LOCAL-AND-MOBILE.md). No production database was imported, no remote branch was pushed, and no cloud deployment or app-store submission was performed.

## What was examined

Baseline commit: `ac6eace` (`fix(dashboard): the maps open on the United States, not the world`). Branch for this work: `codex/local-hybrid-apps`.

The review covered application structure, specification and roadmap, database migrations/seeders, routes and middleware, authentication/authorization, public listing APIs, offer and wishlist behavior, integration configuration, dependency advisories, test coverage, and public/mobile rendering. It combines code inspection with automated tests and local runtime checks; it is not a claim that every line has undergone a formal security audit.

Baseline inventory: **62 models, 70 controllers, 72 service files, 75 migrations, 130 PHP test files, and 142 Blade views**. After adding the mobile endpoints, the runtime exposes **208 routes**, including **26 API routes** and **91 routes under the admin prefix**. Route inventory is saved locally in `.local/routes.json`.

## Architecture and product behavior

| Area | Actual implementation |
| --- | --- |
| Backend | Laravel 11.51.0, flat repository layout, service-layer business operations |
| Web frontend | Blade + Livewire/Volt, Vite/Tailwind asset build already present in the repository |
| Identity | Breeze web sessions; Sanctum 4.3.2 bearer tokens for API clients |
| Authorization | User role enum plus granular permission catalog/RBAC, ownership checks on member resources |
| Data | MySQL, Redis cache/queue, Eloquent models, forward migrations |
| Property product | Advertising/listings, owner inquiries and time-limited offers; accommodation booking routes retired |
| Payment product | NMI for advertising/member-service activation; old booking/payment records retained |
| Signing | DocuSign JWT/API and signed Connect webhook processing, private document storage |
| Support | Help article search, support requests, optional Anthropic chat with graceful fallback |
| Operations | Member queues, listings/media/availability, offers, contracts, settings, fees, audit history and analytics |
| Hosting | Laravel Cloud branch/environment workflow documented in the repository; unchanged here |

Public API browsing only exposes active properties. Web and app use the same property identifiers, photos, amenities, status, and prices. Rentals use the website's `7 days / 6 nights` caption; sale listings use `Asking price`. No accommodation checkout was added to the app.

## Local setup findings and resolutions

1. **Existing local work needed preservation.** `vaytoven-front` pointed at the same repository but contained extensive uncommitted work on an older commit. The new checkout is `vaytoven-apps`; the old working tree was left untouched.
2. **PHP requirement mismatch.** XAMPP provided PHP 8.2.4. `composer.json` declares `^8.3`, but locked Symfony packages require at least PHP 8.4.1. PHP 8.4.25 is used by this setup; lockfile requirements were not bypassed. The runbook explicitly corrects the practical local requirement.
3. **MySQL trigger prerequisite.** The append-only `tracking_events` migration needs trigger-creation permission when binary logging is on. The dedicated local instance uses `log_bin_trust_function_creators=1`. After the first partial migration, a new working database was created rather than dropping data or editing shipped migrations.
4. **Separate services.** MySQL runs on 3307, Redis on 6380, Laravel on 8000, avoiding the older XAMPP application/database setup. Application key, local credentials, runtime data, logs, SDKs and artifacts live in ignored files.
5. **Test memory.** The initial full PHP run exhausted the default 128MB limit during PDF rendering. At 768MB, the complete suite passes, with a measured peak near 199MB. This was a runner resource limit, not a test assertion failure.
6. **Actual data scope.** The checkout seeds ten sample properties and demo users. It does not contain or fetch the production customer database, private uploads, payment keys, or signing credentials.

## Mobile implementation and API coverage

| Surface | Delivered behavior |
| --- | --- |
| Explore | Branded home, destination/property search, capacity/price filters, loading/empty/error handling and pagination |
| Listing detail | Live property description, photo gallery, amenities, pricing caption, share, save and inquiry/offer forms |
| Saved | Server-backed private collection shared with the website; explicit idempotent save/unsave actions |
| Offers | Buyer submission, sent/received list, integer cents, 24-hour state handling, owner accept/decline and notes |
| Account | Existing API registration/sign-in, legal-version review, forced first password change, password update and token revocation |
| Support | Existing chat API and its unavailable-provider reply, help/contact handoff |
| Platform | Native HTTP, browser handoff, sharing, Android back navigation, branded icons and splash colors |
| Full member/admin work | Existing website opened in a system browser; a separate web sign-in may be required |

New routes under `/api/v1/mobile`:

| Method | Route | Purpose |
| --- | --- | --- |
| GET | `/account` | User and legal-review state |
| POST | `/terms` | Accept exactly the legal versions reviewed |
| POST | `/password` | Change password and revoke other API tokens |
| GET | `/saved` | Account-private active saved properties |
| PUT / DELETE | `/saved/{property}` | Idempotent save / unsave |
| GET | `/offers` | Own sent offers and offers on owned listings |
| POST | `/properties/{property}/offers` | Inquiry/offer submission |
| POST | `/offers/{offer}/respond` | Owner-only acceptance/decline |

Authentication, inactive-account checks and current-term gates protect these endpoints. The password-change route remains reachable for an account holding a temporary password. New offer requests validate integer cents and date pairs. Submission/response use transactions and row locks; no new tables were required. Critical writes reuse the existing audit/activity services.

Tokens are held in process memory. This avoids storing bearer credentials in localStorage or plain native preferences, but it means users sign in after app restart. Persistent secure login and server token lifetime policy remain future work.

## Security dependency review

The initial npm audit reported **eight website dependency findings**. Compatible updates were applied; the final website npm audit reports **zero**.

The mobile tooling initially reported three moderate findings through `xcode → uuid`. A narrow override updates that development dependency to patched `uuid ^11.1.1`. Xcode project parsing, UUID generation, and native sync were checked; the resulting install audit reports **zero**. This is development tooling, not a runtime mobile library.

The initial Composer audit reported **41 advisory records across 12 packages**. Compatible updates addressed Dompdf, Guzzle/PSR-7, CommonMark, Livewire, and affected Symfony packages while retaining Laravel 11. The final audit reports **three records on Laravel**, representing two advisory identifiers (the CRLF advisory appears twice in the feed):

- [Temporary signed URL path confusion — GHSA-crmm-hgp2-wgrp](https://github.com/advisories/GHSA-crmm-hgp2-wgrp)
- [Default email validation CRLF injection — GHSA-5vg9-5847-vvmq](https://github.com/advisories/GHSA-5vg9-5847-vvmq)

**A supported, patched Laravel upgrade is release work still required.** The current repository explicitly constrains the architecture to Laravel 11; this task did not silently perform a major framework migration. Audit results identify affected dependencies, not proof of exploitation or a complete assessment of reachability in every route.

Final package examples: Laravel 11.51.0, Sanctum 4.3.2, Livewire 3.8.8, Dompdf 3.1.6, Guzzle 7.15.5. Full advisory snapshots are in `.local/composer-audit-final.json`, `.local/npm-audit-final.json` and the npm logs.

## Documentation and implementation discrepancies

The old SRS and roadmap contain phase statuses that substantially predate the code: they describe Sanctum, settings, tracking, and many controllers as unimplemented. They also describe accommodation bookings and Stripe, while current routes explicitly retire booking operations and use NMI advertising payments. These documents cannot be treated as an accurate feature inventory without reconciliation by the product owner.

The existing marketing mobile page still claimed guests could book and pay. That directly conflicted with its current controllers. Its copy now describes discovery and direct offers, and the local environment exposes the new app preview link. App-store links remain absent because no store listing has been created.

`composer.json`'s PHP 8.3 declaration remains broader than its lockfile allows. The runbook states the effective 8.4.1+ requirement. A future dependency policy should pin the intended platform and test it in CI.

Several web-only workflows have no complete mobile API contract: member listing administration, document signing/downloads, advertising payments, profile editing and full admin operations. The app hands these off to the website instead of inventing unverified API endpoints or pretending those screens are native.

The original browser test suite contains stale assertions, including `base_nightly_cents` instead of the current `price_cents`, and an assumption that `/health` always returns `status=ok` even when advisory services can be degraded. The new mobile suite tests the actual current contract. The PHP suite was run in full; the original complete desktop E2E suite was not certified green by this delivery.

## Verification evidence

- **1,179 PHP tests / 4,455 assertions pass** after the compatible PHP security updates. Tests use in-memory SQLite; the local application uses MySQL 8.4.
- **10 mobile API tests** cover authentication, deactivation with an existing token, price captions, private/idempotent saves, unpublished/own-listing rejection, integer cents, owner-only responses, terms gates, and forced password changes.
- **Four JavaScript unit tests** cover exact integer-cent parsing, money display, HTML escaping, and unsafe URL schemes.
- **Six mobile browser scenarios** cover discovery, filtering, galleries, no-results states, sign-in and saves, submitting a $123.45 offer, owner acceptance, support fallback, invalid login, phone/desktop width and offline notice. A 360px overflow was found and fixed. A later Chrome startup timeout occurred before page load; the affected browsing scenario passed on rerun.
- Website Vite build and mobile build/sync pass. PHP formatting and `git diff --check` pass.
- `/health` responds HTTP 200 with database and Redis healthy; sample property API returns ten records. External mail/payment/signing/AI credentials are not being exercised.
- Android debug build succeeds. The APK's JavaScript/CSS were compared with the current synced source. A release-guard check correctly rejects the local HTTP backend.
- Xcode 26.3 installed and initialized on 2026-09-11. iOS simulator and unsigned iPhone hardware builds pass. The iPhone 17 Pro simulator launches the branded app, and its local property API request returns HTTP 200. Signing and physical-device testing remain outstanding; see the native verification notes for the narrower iOS test scope.

The installed Android app also passed the complete local sign-in → saved listing → offer → owner acceptance → logout smoke test. See [NATIVE-VERIFICATION.md](NATIVE-VERIFICATION.md) for runtime results and commands.

## Before public release

1. Upgrade Laravel to a supported patched major version, reconcile dependency/platform policy, and re-run the complete regression suite.
2. Review the delivered mobile flow, extend any required member/admin screens, and decide whether remembered secure login and push notifications are launch requirements.
3. Deploy the mobile API changes through the repository's development → sandbox → production workflow, then configure the apps with that HTTPS origin.
4. Test concurrency on MySQL and integrations in their sandbox environments: NMI, DocuSign, mail, storage, and support provider.
5. Configure the Apple signing team, complete iOS account/offer and physical-device testing, and prepare signed release artifacts. Android store distribution also requires the owner's signing key and Play Console configuration.
6. Prepare store metadata/privacy declarations, screenshots and review submissions. None were submitted from this local task.

Reference: [Capacitor installation and native project workflow](https://capacitorjs.com/docs/getting-started).
