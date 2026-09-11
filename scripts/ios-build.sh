#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
export DEVELOPER_DIR="${DEVELOPER_DIR:-/Applications/Xcode.app/Contents/Developer}"
: "${VAYTOVEN_API_URL:?Set VAYTOVEN_API_URL to the Laravel server origin.}"
"$DEVELOPER_DIR/usr/bin/xcodebuild" -checkFirstLaunchStatus
mkdir -p .local/artifacts
(
  cd mobile
  node scripts/build.mjs
  npx cap sync ios
)
"$DEVELOPER_DIR/usr/bin/xcodebuild" \
  -project mobile/ios/App/App.xcodeproj -scheme App -configuration Debug \
  -sdk iphonesimulator -destination 'generic/platform=iOS Simulator' \
  -derivedDataPath .local/ios-build CODE_SIGNING_ALLOWED=NO build
VAYTOVEN_IOS_APP="$PWD/.local/ios-build/Build/Products/Debug-iphonesimulator/App.app"
[[ -x "$VAYTOVEN_IOS_APP/App" ]]
/usr/bin/ditto -c -k --sequesterRsrc --keepParent "$VAYTOVEN_IOS_APP" \
  .local/artifacts/Vaytoven-iOS-Simulator.zip
printf 'iOS Simulator app: %s\n' "$VAYTOVEN_IOS_APP"
printf 'Archive: %s/.local/artifacts/Vaytoven-iOS-Simulator.zip\n' "$PWD"
