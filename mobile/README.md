# Vaytoven hybrid apps

One branded interface, packaged for Android and iOS using Capacitor 8. See [the local/mobile runbook](../docs/LOCAL-AND-MOBILE.md) for setup, credentials, native builds, and limitations.

```bash
npm ci
npm run build
```

Open the Laravel server's `/app/` route to preview. To sync native projects, explicitly set the backend origin:

```bash
VAYTOVEN_API_URL=https://your-deployed-laravel-origin.example npm run sync
npm run android  # Android Studio
npm run ios      # Xcode
```

For local debug only:

```bash
VAYTOVEN_API_URL=http://127.0.0.1:8000 VAYTOVEN_ALLOW_LOCAL_HTTP=1 npm run sync
```

Android devices need `adb reverse tcp:8000 tcp:8000`. The local APK depends on Laravel running on the attached Mac. Xcode 26.3 is installed on this Mac; simulator and unsigned iPhone builds pass. From the repository root, run `VAYTOVEN_API_URL=http://localhost:8000 VAYTOVEN_ALLOW_LOCAL_HTTP=1 bash scripts/ios-build.sh` to produce `.local/artifacts/Vaytoven-iOS-Simulator.zip`. An installable iPhone IPA still requires Apple signing. Native bundles ship the UI locally; backend/API deployment remains a separate operation.

The `xcode` development-tool dependency is narrowly overridden to `uuid ^11.1.1` to address GHSA-w5hq-g745-h8pq. Its `uuid.v4()` use and Xcode project parsing were verified, along with Capacitor native sync. This dependency is tooling only and is not bundled into the mobile web interface.
