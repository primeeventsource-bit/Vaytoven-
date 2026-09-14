# Local website and hybrid apps

Created on 2026-09-10 from GitHub `main` at `ac6eace` in the separate `vaytoven-apps` checkout. Work is on `codex/local-hybrid-apps`. The older `vaytoven-front` checkout and its uncommitted changes were preserved.

## Open the running project

- Website: http://127.0.0.1:8000
- Mobile app preview: http://127.0.0.1:8000/app/
- Account sign-in: http://127.0.0.1:8000/login
- Admin: http://127.0.0.1:8000/admin
- Health: http://127.0.0.1:8000/health

This is an isolated development installation with **seeded example listings**, not a copy of the production customer database. Mail uses the local log; payment, signing, AI-provider, and external notification credentials are unset.

Local traveler, host, and admin credentials are in `.local/demo-credentials.json` (ignored by Git and readable only by the file owner). The host account owns the seeded catalogue. Use the admin account to inspect the website administration area. No passwords are committed to the repository.

## Start it again on this Mac

From the repository root:

```bash
bash scripts/local-start.sh
```

When running through a managed command runner, use `bash scripts/local-start.sh --foreground` and keep that session open. Some runners terminate background children when their command exits. The script waits for MySQL, Redis and the HTTP health route before reporting the URLs.

The setup uses these dedicated services:

| Component | Configuration |
| --- | --- |
| PHP | `/opt/homebrew/opt/php@8.4/bin/php`, with Redis extension |
| MySQL | 8.4, `127.0.0.1:3307`, data in `.local/mysql` |
| Database | `vaytoven_apps_local`, user `vaytoven_local` |
| Redis | `127.0.0.1:6380`, data in `.local/redis` |
| Laravel | `127.0.0.1:8000` |
| Queue | Local Redis worker, mail delivery to log |

The website is served by PHP 8.4 separately from XAMPP's PHP 8.2. PHP 8.3 cannot install the current lockfile: Symfony packages require PHP 8.4.1+. Both PHP 8.3 and PHP 8.4 were installed during diagnosis; this project uses 8.4.

On a different computer, install PHP 8.4.1+ with the extensions required by `composer check-platform-reqs`, MySQL 8.4, Redis, Node 22+, Composer, and npm. Copy `.env.example` to `.env`, generate an app key, and configure a **new local** database. Use `APP_ENV=local`, `APP_DEBUG=true`, `APP_URL=http://127.0.0.1:8000`, `LOG_CHANNEL=single`, `MAIL_MAILER=log`, and local database/Redis connection values. Do not copy production secrets.

```bash
composer install
npm ci
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm run build
npm --prefix mobile ci
npm --prefix mobile run build
php artisan serve --host=127.0.0.1 --port=8000
```

MySQL must permit the append-only audit triggers. This isolated local instance uses `log_bin_trust_function_creators=1`; `scripts/local-start.sh` includes it on restart. An initial partial database named `vaytoven_local` was preserved after that prerequisite was discovered. The working application uses `vaytoven_apps_local`.

## Hybrid architecture

`mobile/www` contains a shared HTML/CSS/JavaScript interface; no React or additional frontend framework was introduced. Capacitor packages the same interface inside Android and iOS applications. Native plugins supply browser handoff, sharing, and Android back navigation. API calls use Capacitor's native HTTP transport on devices and same-origin fetch in the browser preview.

The design uses the website's brand mark, cream `#FBF8F3` background, ink `#1A1426`, pink `#FF3D8A`, magenta `#D63384`, purple `#7B2CBF`, Source Serif 4 headings, and Geist body typography.

Implemented in the shared app:

- Destination/property search, group-size and maximum-price filters, pagination, property details, photo gallery, amenities, and sharing.
- Email/password registration and sign-in against the existing Sanctum API.
- Explicit acceptance of the current terms/privacy versions; changed versions must be reviewed again.
- Forced initial password changes and password updates; other API tokens are revoked after an update.
- Saved properties stored in the same wishlist tables used by the website, private to each account.
- Guest inquiries/offers in integer cents, owner accept/decline with notes, and offer status/expiry.
- Support chat with the backend's honest unavailable-provider fallback, plus help/contact links.
- Loading, validation, empty, offline, and connection-failure states; phone and desktop layouts.

The app follows the current **advertising and direct-offer** business model. It does not create accommodation reservations or collect accommodation payments. Rental price labels come from the website's listing type (`7 days / 6 nights`); sale prices are labeled `Asking price`.

Host/member administration, document signing, contract downloads, profile editing, and advertising payments open the existing website in the system browser. Web login is separate from app login. These are browser handoffs, not rebuilt native screens. Push notifications, biometric login, persistent secure login, and app-store distribution are not implemented.

Tokens live only in app-process memory, never localStorage or unencrypted native preferences. Closing/reloading the app requires sign-in again. Server-side saves/offers persist. A future remembered-login feature should use platform secure storage and a token-expiration policy.

## Android

Project: `mobile/android`. Application ID: `com.vaytoven.app`. Minimum Android API: 24. Target/compile API: 36. Native icons and launch colors use the website branding.

The local debug build points to `http://127.0.0.1:8000`. On an Android device, that is the device's loopback address; connect it to this Mac with USB debugging and reverse the port:

```bash
.local/android-sdk/platform-tools/adb reverse tcp:8000 tcp:8000
.local/android-sdk/platform-tools/adb install -r mobile/android/app/build/outputs/apk/debug/app-debug.apk
.local/android-sdk/platform-tools/adb shell am start -n com.vaytoven.app/.MainActivity
```

Keep Laravel running while using the local app. This debug APK is for local evaluation; it will not connect to this Mac when distributed to somebody else's phone without the connection described above.

To rebuild:

```bash
VAYTOVEN_API_URL=http://127.0.0.1:8000 VAYTOVEN_ALLOW_LOCAL_HTTP=1 npm --prefix mobile run sync
cd mobile/android
JAVA_HOME=/opt/homebrew/opt/openjdk@21/libexec/openjdk.jdk/Contents/Home \
ANDROID_HOME="$PWD/../../.local/android-sdk" \
GRADLE_USER_HOME="$PWD/../../.local/gradle" \
./gradlew :app:assembleDebug
```

Only debug builds permit HTTP to loopback/emulator addresses. Release builds have a guard that rejects a missing/non-HTTPS backend. Use an HTTPS deployment containing these API changes, sync again, configure your own signing key, and build a signed AAB for store submission. No production API deployment or store submission has been performed.

The APK was installed and tested on an Android 36 ARM64 emulator, including login, saves, offer submission and owner acceptance. See [NATIVE-VERIFICATION.md](NATIVE-VERIFICATION.md) for results and repeatable commands.

## iOS

Project: `mobile/ios/App/App.xcodeproj`. Dependencies use Swift Package Manager. Deployment target: iOS 15+. App ID: `com.vaytoven.app`. Branding includes an opaque 1024px app icon and launch artwork.

Xcode 26.3 is installed at `/Applications/Xcode.app`, its agreement was accepted with the owner's approval, and the iOS 26.3 simulator runtime is installed. Both the simulator and unsigned iPhone hardware targets compiled successfully on 2026-09-11. The app launched in the iPhone 17 Pro simulator and its property API request returned HTTP 200 from local Laravel.

```bash
# Simulator on the same Mac:
VAYTOVEN_API_URL=http://localhost:8000 VAYTOVEN_ALLOW_LOCAL_HTTP=1 bash scripts/ios-build.sh
npm --prefix mobile run ios
```

The script produces `.local/artifacts/Vaytoven-iOS-Simulator.zip`, containing `App.app` for an Apple silicon simulator. Start Laravel, unzip it, boot an iPhone simulator, then run `xcrun simctl install booted /path/to/App.app` and `xcrun simctl launch booted com.vaytoven.app`.

This ZIP is not an installable iPhone IPA. No valid code-signing identities are configured on this Mac. For a physical iPhone or distribution, set `VAYTOVEN_API_URL` to your deployed HTTPS Laravel origin, sync again, add your Apple account in Xcode Settings, select your development team in Signing & Capabilities, and sign/archive from Xcode. The native local-network allowance does not permit arbitrary insecure internet traffic.

## Build and verification commands

```bash
/opt/homebrew/opt/php@8.4/bin/php -d memory_limit=768M vendor/bin/phpunit
npm run build
npm --prefix mobile test
npx playwright test --config=mobile/playwright.config.js
```

The mobile browser tests only target localhost and read `.local/demo-credentials.json`. They intentionally create local offers and terms acceptances. `phpunit.xml` runs PHP tests against in-memory SQLite; the running website and native app use MySQL. MySQL concurrency behavior needs separate load testing before release.

`npm --prefix mobile run build` creates the ignored `/public/app/` browser preview. Native sync requires an explicit `VAYTOVEN_API_URL`; it cannot silently choose a production server. Do not put API keys or credentials into this variable. Native bundled settings contain only a server origin.

See `REPOSITORY-ANALYSIS.md` for findings, verification evidence, and remaining release work.
