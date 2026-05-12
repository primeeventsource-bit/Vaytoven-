#!/usr/bin/env bash
#
# fetch-maxmind.sh — pulls the free MaxMind GeoLite2 City database onto the
# deployed environment so MaxMindGeoIpService has data to read.
#
# Wired into the Laravel Cloud deploy hook BEFORE `php artisan migrate` so
# that whatever the container starts serving has fresh geo data. Designed to
# never block a deploy: if credentials are missing or the download fails,
# logs a warning and exits 0 — the app silently falls back to NoOpGeoIpService.
#
# Required env vars on the deployed environment (set via `cloud env:update`):
#   MAXMIND_ACCOUNT_ID    — your numeric MaxMind account ID
#   MAXMIND_LICENSE_KEY   — license key generated at maxmind.com → My Account
#   MAXMIND_MMDB_PATH     — absolute path where the .mmdb will be written
#                           (typically /var/www/html/storage/app/geoip/GeoLite2-City.mmdb)
#
# Free signup: https://www.maxmind.com/en/geolite2/signup
# License keys: https://www.maxmind.com/en/accounts/current/license-key
#
# The Anonymous IP DB (VPN/Tor/datacenter detection) is a separate paid
# product; if MAXMIND_ANONYMOUS_MMDB_PATH is set and credentials are paid,
# we'd add a second download here. For now the script handles the free
# City DB only.

set -u  # error on unset vars
# Do NOT set -e — we want this script to succeed even if the download fails,
# so deploys don't break when MaxMind has a hiccup.

log() {
    printf "[fetch-maxmind] %s\n" "$*"
}

if [ -z "${MAXMIND_LICENSE_KEY:-}" ] || [ -z "${MAXMIND_ACCOUNT_ID:-}" ]; then
    log "MAXMIND_LICENSE_KEY / MAXMIND_ACCOUNT_ID not set — skipping. GeoIP will fall back to NoOpGeoIpService."
    exit 0
fi

DEST="${MAXMIND_MMDB_PATH:-storage/app/geoip/GeoLite2-City.mmdb}"
DEST_DIR="$(dirname "$DEST")"

mkdir -p "$DEST_DIR" || {
    log "Could not create $DEST_DIR — exiting without touching anything."
    exit 0
}

TMP_DIR="$(mktemp -d)" || {
    log "mktemp failed — exiting."
    exit 0
}
trap 'rm -rf "$TMP_DIR"' EXIT

TAR_PATH="$TMP_DIR/GeoLite2-City.tar.gz"

log "Fetching GeoLite2-City.tar.gz from MaxMind…"
HTTP_CODE=$(curl -sS -L -o "$TAR_PATH" -w "%{http_code}" \
    -u "${MAXMIND_ACCOUNT_ID}:${MAXMIND_LICENSE_KEY}" \
    "https://download.maxmind.com/geoip/databases/GeoLite2-City/download?suffix=tar.gz" || echo "000")

if [ "$HTTP_CODE" != "200" ]; then
    log "Download returned HTTP $HTTP_CODE — leaving existing $DEST untouched (if any)."
    exit 0
fi

# The tarball contains a versioned directory (GeoLite2-City_YYYYMMDD/) with
# the .mmdb inside. Extract everything, then find and move the .mmdb file.
if ! tar -xzf "$TAR_PATH" -C "$TMP_DIR"; then
    log "tar extraction failed — leaving existing $DEST untouched."
    exit 0
fi

MMDB_SRC="$(find "$TMP_DIR" -name 'GeoLite2-City.mmdb' -type f | head -n 1)"
if [ -z "$MMDB_SRC" ]; then
    log "No .mmdb file inside the tarball — exiting."
    exit 0
fi

# Atomic move so the running process never sees a partial file.
mv "$MMDB_SRC" "$DEST.tmp" && mv "$DEST.tmp" "$DEST"

SIZE_MB=$(( $(stat -c '%s' "$DEST" 2>/dev/null || stat -f '%z' "$DEST") / 1024 / 1024 ))
log "Wrote $DEST (~${SIZE_MB} MB). MaxMindGeoIpService will activate on next request."
