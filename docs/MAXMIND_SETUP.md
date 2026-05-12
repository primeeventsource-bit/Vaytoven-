# MaxMind GeoIP setup

The Vaytoven backend uses MaxMind's GeoLite2 City database for IP → country/region/city/lat/lng enrichment. Touched by every login event (`login_sessions`), every property view (`property_views`), and the host/member dashboard map.

When the database file isn't present, `App\Providers\AppServiceProvider` silently binds `GeoIpService` to `NoOpGeoIpService` — real visits still load, they just don't get geo-enriched and don't show up on the dashboard map.

## One-time MaxMind setup

1. Sign up for a free MaxMind account: <https://www.maxmind.com/en/geolite2/signup>
2. After confirming your email, go to <https://www.maxmind.com/en/accounts/current/license-key> and generate a license key. Save the key + your numeric account ID.

## Per-environment configuration

Set these three env vars on the Laravel Cloud environment (`cloud env:update <env-id> --key X --value Y`):

| Env var | Value |
|---|---|
| `MAXMIND_ACCOUNT_ID` | numeric account ID from your MaxMind account page |
| `MAXMIND_LICENSE_KEY` | license key from the page above |
| `MAXMIND_MMDB_PATH` | `/var/www/html/storage/app/geoip/GeoLite2-City.mmdb` (on Laravel Cloud) |

Then update the **deploy command** to run the fetch script before `migrate`:

```
bash bin/fetch-maxmind.sh; php artisan migrate --force
```

The semicolon (not `&&`) is intentional — if the download fails, migrations still run and the deploy still succeeds. The app just stays on `NoOpGeoIpService` until the next successful deploy.

## What happens at deploy

1. Build phase: `composer install --no-dev …` produces the deployable app.
2. Deploy phase: container starts → runs the deploy command:
   - `bin/fetch-maxmind.sh` checks for the env vars. If both are present, it downloads `GeoLite2-City.tar.gz` (~70 MB), extracts the `.mmdb` file, and writes it atomically to `MAXMIND_MMDB_PATH`.
   - `php artisan migrate --force` runs as normal.
3. First request after deploy: `AppServiceProvider::register()` sees that `MAXMIND_MMDB_PATH` is readable and binds `GeoIpService` → `MaxMindGeoIpService` (wrapped in `CachedGeoIpService`).
4. Every subsequent IP lookup is served from MaxMind, cached in Redis.

## Verifying

After a deploy with credentials configured, tail the deploy log — you should see:

```
[fetch-maxmind] Fetching GeoLite2-City.tar.gz from MaxMind…
[fetch-maxmind] Wrote /var/www/html/storage/app/geoip/GeoLite2-City.mmdb (~75 MB). MaxMindGeoIpService will activate on next request.
```

Then hit any `/properties/{id}` page and the resulting `property_views` row should have non-null `country`, `city`, `latitude`, `longitude`. Existing seeded demo views (all in RFC 5737 documentation IP ranges like `198.51.100.x`) won't change — those were inserted with hardcoded geo data and the seeder bypasses GeoIpService.

## Fallback options (no license)

If you don't want to bother with a MaxMind account, two alternatives:

- **Cloudflare headers** — Laravel Cloud sits behind Cloudflare, which sends `cf-ipcountry`, `cf-iplatitude`, `cf-iplongitude` on every request. A `CloudflareHeaderGeoIpService` could be wired in `AppServiceProvider` as a higher-priority fallback. Country-level accuracy, free.
- **`NoOpGeoIpService`** (current default when MaxMind isn't configured) — geo fields stay null; real visits don't appear on the map, but everything else works. Seeded demo data on the dashboard remains visible because it was inserted with hardcoded coords.

## Rotating the license key

MaxMind license keys don't expire, but if you ever need to rotate one:

```
cloud env:update <env-id> --key MAXMIND_LICENSE_KEY --value <new-key>
```

The next deploy will pick up the new key. The downloaded `.mmdb` file refreshes weekly from MaxMind, so just redeploying weekly keeps the data current.
