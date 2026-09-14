# Native verification — updated 2026-09-11

## Android result

The debug APK compiled, installed, launched, and completed the account/offer workflow in an **Android 36 Google APIs ARM64 emulator**. Its native WebView origin was `https://localhost/`, with requests sent to Laravel at `http://127.0.0.1:8000` through Capacitor HTTP and ADB reverse forwarding.

Verified inside the installed app:

- Loaded all ten seeded properties from Laravel/MySQL.
- Signed in as the local traveler and opened the account's saved property.
- Submitted a $137.25 offer and displayed its server response.
- Signed out, signed in as the listing owner, and accepted that same offer.
- Displayed the accepted status and signed out successfully.
- Inspected the rendered branded home screen and captured home/offer screenshots.

The test uses only ignored local demo credentials and creates a local test offer. It does not contact production accounts or submit a payment.

Artifact: `mobile/android/app/build/outputs/apk/debug/app-debug.apk` (approximately 4.1 MB). Android `apksigner verify --verbose` passes with APK Signature Scheme v2. This uses a development signing key, not a store distribution key. Bundled JavaScript/CSS match the synced source. The release build guard correctly rejects the local HTTP configuration.

Evidence in the ignored `.local/` directory:

- `android-build.log`
- `android-smoke.log`
- `android-home-verified.png`
- `android-offer-verified.png`
- `release-guard-check.log`

## Repeat on this Mac

Start Laravel with `bash scripts/local-start.sh --foreground` in one terminal. Start the prepared emulator in another terminal, from the repository root:

```bash
ANDROID_HOME="$PWD/.local/android-sdk" \
ANDROID_AVD_HOME="$PWD/.local/avd" \
.local/android-sdk/emulator/emulator -avd VaytovenLocal \
  -no-audio -gpu swiftshader -no-snapshot -memory 2048
```

After Android finishes booting:

```bash
.local/android-sdk/platform-tools/adb reverse tcp:8000 tcp:8000
.local/android-sdk/platform-tools/adb install -r mobile/android/app/build/outputs/apk/debug/app-debug.apk
.local/android-sdk/platform-tools/adb shell am force-stop com.vaytoven.app
.local/android-sdk/platform-tools/adb shell am start -n com.vaytoven.app/.MainActivity
node mobile/tests/android-smoke.mjs
```

The smoke script attaches to the installed debug WebView through Playwright's Android support. Run against the dedicated local emulator with the local backend and `.local/demo-credentials.json` present. It expects a fresh signed-out app on the Explore screen.

## iOS result — 2026-09-11

Xcode 26.3 (17C529) was installed from the owner's Apple download and initialized after explicit approval of the Xcode/SDK agreement. The installed iOS 26.3 runtime reports version 26.3.1 (23D8133).

- Simulator Debug build: **BUILD SUCCEEDED**, with code signing disabled.
- Physical iPhone ARM64 Debug build: **BUILD SUCCEEDED**, with code signing disabled.
- Installed and launched `com.vaytoven.app` on the iPhone 17 Pro simulator.
- Visually checked the branded home screen, hero image, search field and bottom navigation.
- Native network logs confirm the request to `http://localhost:8000/api/v1/properties` received HTTP 200. Local Laravel health checks also pass.

Artifact: `.local/artifacts/Vaytoven-iOS-Simulator.zip` (approximately 2 MB), containing the Apple silicon simulator `App.app`. It requires the local Laravel server. This is a simulator bundle, not a signed iPhone IPA. The unsigned device build is at `.local/ios-device-build/Build/Products/Debug-iphoneos/App.app` and cannot be installed on an iPhone without signing.

Rebuild from the repository root:

```bash
VAYTOVEN_API_URL=http://localhost:8000 VAYTOVEN_ALLOW_LOCAL_HTTP=1 bash scripts/ios-build.sh
xcrun simctl install booted .local/ios-build/Build/Products/Debug-iphonesimulator/App.app
xcrun simctl launch booted com.vaytoven.app
```

For the unsigned device compile check:

```bash
xcodebuild -project mobile/ios/App/App.xcodeproj -scheme App -configuration Debug \
  -sdk iphoneos -destination 'generic/platform=iOS' \
  -derivedDataPath .local/ios-device-build CODE_SIGNING_ALLOWED=NO build
```

Evidence: `.local/ios-build-final.log`, `.local/ios-device-build.log`, `.local/ios-runtime.log`, and `.local/ios-home-verified.png`. iOS account/offer interactions were not certified in this run: simulator UI automation returned `noWindowsAvailable` for input despite successful screenshots and launch. The Android account/offer result above does not substitute for iOS workflow testing.

## Limits

This is emulator verification, not a physical-device compatibility matrix. Native sharing, browser handoff, device rotation and background/foreground transitions still need broader device QA before distribution. MySQL concurrency/load and external payment/signing integrations were not exercised by this smoke test.

No valid Apple code-signing identities are configured on this Mac. The owner's Apple signing account/team and provisioning are needed for an installable iPhone IPA. Full iOS workflow and physical-device testing remain required. Neither app was submitted to a store.
