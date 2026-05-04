# Laravel Cloud Deployment Runbook

The production hosting target is **Laravel Cloud** (cloud.laravel.com).
Configuration is primarily dashboard-driven; the `cloud` CLI handles env
sync, deploys, and logs from your machine.

This runbook walks the first-time setup. Each section calls out whether
you (the human) are clicking in the dashboard, running a CLI command, or
both.

## Project state (already in place)

- Laravel Cloud account exists. App: `vaytoven` in org `primeeventsource`,
  region `us-east-2`, PHP 8.5.
- GitHub repo `primeeventsource-bit/Vaytoven-` connected.
- `cloud` CLI installed and authenticated locally
  (`C:\Users\prime\AppData\Local\Composer\bin\cloud.bat`,
  config at `C:\Users\prime\.config\cloud\config.json`).
- Four git branches exist:
  `development`, `sandbox`, `staging`, `production`.
- Build command set: `composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader`.
- Deploy command set: `php artisan migrate --force`.

## Environments overview

Four Laravel Cloud environments, each tied 1:1 to a branch. Pushing to a
branch triggers a deploy of just that environment.

| Branch        | Environment | APP_ENV       | APP_DEBUG | URL                                | Purpose                             |
|---------------|-------------|---------------|-----------|------------------------------------|-------------------------------------|
| `development` | development | `local`       | `true`    | `*.laravel.cloud` default          | Active dev, throwaway data          |
| `sandbox`     | sandbox     | `sandbox`     | `true`    | `*.laravel.cloud` (or sandbox.vaytoven.com) | Stripe + DocuSign sandbox flows     |
| `staging`     | staging     | `staging`     | `false`   | `staging.vaytoven.com`             | Pre-prod rehearsal, prod-like config |
| `production`  | production  | `production`  | `false`   | `vaytoven.com` + `www.vaytoven.com` | Live customer traffic               |

Promotion flow: `development` → `sandbox` → `staging` → `production`,
each step a PR.

## Phase A — Environment setup (dashboard, ~10 min per env)

Repeat for each of the four environments. In the dashboard:

1. Create the environment (or confirm it exists).
2. Bind it to the matching git branch from the table above. Push-to-deploy
   then fires for that branch only.
3. Confirm the build and deploy commands are inherited from the app-level
   defaults. Override only if the environment needs something different —
   typically not.

## Phase B — Service bindings (dashboard, ~5 min per env)

For **every** environment, attach:

1. **Postgres** — managed Postgres. Each environment gets its own database;
   never share. Auto-injects `DB_CONNECTION`, `DB_HOST`, `DB_PORT`,
   `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` at runtime. **Do not set
   these manually** — they come from the binding.

2. **Redis** — managed Redis for sessions, queues, cache. Auto-injects
   `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`. Same rule.

3. **Object Storage** — only required on `production` for first boot.
   Add to `staging` before launch. `sandbox`/`development` can skip until
   media uploads matter.

Cost note: each environment has its own Postgres + Redis. Four full
environments is roughly 4× the cost. Two ways to compress without losing
the workflow:

- Combine `development` + `sandbox` into a single shared non-prod env that
  follows whichever branch you're testing.
- Enable **Hibernation** on `development` and `sandbox` so compute drops
  to zero when idle. Postgres + Redis still cost something but compute
  bills nearly disappear.

## Phase C — Environment variables (dashboard, ~10 min per env)

Per-environment unique values (paste into each environment's panel):

| Variable     | development                                    | sandbox                                        | staging                                        | production                                     |
|--------------|------------------------------------------------|------------------------------------------------|------------------------------------------------|------------------------------------------------|
| `APP_KEY`    | `base64:L4ITtXPmRC8uUJ0i0zX6oJjEra4FRjZDr05jXKM+vek=` | `base64:N7FpeOKlHmjShyWVSJS4+Ve44OVNQLLIS+IUaxne8/U=` | `base64:I2hBu81lMXU3tSfNernPZxnDKwu7KGbzEekSYQuHqfE=` | `base64:HD4PyfTcNbESvT59nwbV3l+ZLAjM1vLdIxiKXh2xNPc=` |
| `APP_ENV`    | `local`                                        | `sandbox`                                      | `staging`                                      | `production`                                   |
| `APP_DEBUG`  | `true`                                         | `true`                                         | `false`                                        | `false`                                        |
| `LOG_LEVEL`  | `debug`                                        | `debug`                                        | `info`                                         | `info`                                         |
| `APP_URL`    | (Cloud's `*.laravel.cloud` URL)                | (Cloud's `*.laravel.cloud` URL)                | `https://staging.vaytoven.com`                 | `https://vaytoven.com`                         |

Common values (paste identically into all four):

```
APP_NAME="Vaytoven Rentals"

LOG_CHANNEL=stderr

SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=false

MAIL_MAILER=log              # change once you wire Postmark/SES/Resend
MAIL_FROM_ADDRESS=hello@vaytoven.com
MAIL_FROM_NAME="Vaytoven Rentals"
```

Per-environment third-party config (paste into each panel with the right
values for that env):

- **Stripe** — test keys for `development`/`sandbox`/`staging`, live keys
  only for `production`.
  ```
  STRIPE_KEY=
  STRIPE_SECRET=
  STRIPE_WEBHOOK_SECRET=
  ```
- **DocuSign** — sandbox URLs for non-prod, production URLs for prod.
  ```
  DOCUSIGN_OAUTH_BASE=https://account-d.docusign.com   # account.docusign.com on prod
  DOCUSIGN_API_BASE=https://demo.docusign.net          # www.docusign.net on prod
  DOCUSIGN_INTEGRATION_KEY=
  DOCUSIGN_USER_ID=
  DOCUSIGN_ACCOUNT_ID=
  DOCUSIGN_PRIVATE_KEY=
  DOCUSIGN_HMAC_KEYS=
  ```
- **Object Storage** (only after binding):
  ```
  FILESYSTEM_DISK=s3
  ```

**Do NOT set in dashboard** (these come from service bindings):

```
DB_CONNECTION  DB_HOST  DB_PORT  DB_DATABASE  DB_USERNAME  DB_PASSWORD
REDIS_HOST  REDIS_PORT  REDIS_PASSWORD
AWS_ACCESS_KEY_ID  AWS_SECRET_ACCESS_KEY  AWS_DEFAULT_REGION
AWS_BUCKET  AWS_USE_PATH_STYLE_ENDPOINT  AWS_ENDPOINT
```

## Phase D — Build and deploy hooks (dashboard, ~5 min, app-wide)

Defaults already configured. Verify:

- **Install command**: `composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader`
- **Build command**: `npm ci && npm run build` — currently disabled. Enable
  only after we move from inline CSS/JS in `welcome.blade.php` to Vite
  bundles. Re-enabling without committing `public/build/` to the repo
  requires this command.
- **Deploy command** (post-deploy hook):
  ```
  php artisan migrate --force
  php artisan storage:link
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  php artisan event:cache
  ```

## Phase E — Workers and scheduler

**Worker** (per environment in dashboard):

- Command: `php artisan queue:work --tries=3 --max-time=300`
- Connection: `redis`
- Queue: `default`
- Min/max instances:
  - `development`: 0 / 1
  - `sandbox`: 0 / 1
  - `staging`: 1 / 2
  - `production`: 1 / 3

`--max-time=300` recycles each worker every 5 minutes to prevent memory
creep without disrupting jobs.

**Scheduler**: runs automatically on every environment. Cloud reads
`routes/console.php` and triggers `schedule:run` every minute. Today the
file only registers Laravel's `inspire` example, so nothing meaningful
runs. Add real schedules to `routes/console.php` when needed.

## Phase F — Domains and SSL

| Environment | Domain                                       | When to add                                  |
|-------------|----------------------------------------------|----------------------------------------------|
| development | (none — use the Cloud `*.laravel.cloud` URL) | —                                            |
| sandbox     | (none, or `sandbox.vaytoven.com` if budget allows) | Optional                                |
| staging     | `staging.vaytoven.com`                       | Before staging is used to test webhooks      |
| production  | `vaytoven.com` and `www.vaytoven.com`        | At apex cutover (Phase I), not before        |

For each custom domain, the dashboard shows a CNAME target. Add the
record at your DNS provider; SSL provisions automatically.

## Phase G — Webhook re-pointing (per environment)

Each environment has its own webhook URL. In the provider's dashboard
add **separate** endpoints per environment, with **separate** signing
secrets pasted into the matching environment's env panel.

- **Stripe** (dashboard.stripe.com → Webhooks):
  - sandbox + staging → use Stripe **test mode** webhooks
  - production → live mode webhook
  - Each gets its own `STRIPE_WEBHOOK_SECRET`.
  - Note: there is no Stripe webhook route in the codebase yet
    (`/webhooks/stripe`). Add the route + handler before configuring.
- **DocuSign** (account.docusign.com → Connect):
  - non-prod environments → DocuSign **sandbox** Connect
    (`account-d.docusign.com`)
  - production → DocuSign production Connect
  - Each gets its own `DOCUSIGN_HMAC_KEYS` (pipe-delimited if rotating).
  - Route already exists at `/webhooks/docusign`.

Until each environment is live and SSL is green, leave these unconfigured
to avoid noisy webhook failures in the provider's dashboard.

## Phase H — First deploy and smoke test

Recommended order: **deploy `development` first**, validate, then promote
forward via PRs.

1. Push the prepared commits to `development` (this happens via the
   normal git flow).
2. Watch the build + deploy logs in the development environment panel.
   First build is ~3-5 min.
3. Smoke-test the development URL:

   - [ ] `GET /` returns the welcome page.
   - [ ] `GET /up` returns 200.
   - [ ] Submit the member enquiry form. Check the DB viewer for a row
         in `members_enquiries`.
   - [ ] Logs panel shows no fatal errors after the form submit.
   - [ ] `Redis::ping()` works (via `cloud ssh` → `php artisan tinker`).

4. If green: PR `development` → `sandbox`, repeat smoke test.
5. PR `sandbox` → `staging`, run real DocuSign sandbox + Stripe test
   transactions end-to-end.
6. PR `staging` → `production` only after staging has been quiet for at
   least a few hours under whatever stakeholder validation you do.

## Phase I — Apex cutover

When `production` deploy is green and `staging` has run a real
end-to-end transaction:

1. In the production environment, add `vaytoven.com` and
   `www.vaytoven.com`.
2. Update DNS:
   - `vaytoven.com` → CNAME / ALIAS to the production env's Cloud target.
   - `www.vaytoven.com` → CNAME to apex (or to Cloud target).
3. Wait for SSL provisioning (~5–15 min after DNS propagates).
4. Update Stripe + DocuSign webhook URLs from `staging.vaytoven.com` (or
   `*.laravel.cloud`) to the apex domain.
5. Watch error rate for an hour.

## Phase J — Decommission Azure App Service

Only after **7 quiet days** on Laravel Cloud production:

1. Delete the Azure App Service `Vayrepo`.
2. Delete the Azure deploy workflow `.github/workflows/main_vayrepo.yml`.
3. Cancel related Azure resources (App Service Plan, etc.) to stop
   billing.

Keep the GitHub OIDC secrets for at least one more week as rollback
insurance. Delete them after the 7-day grace.

## Going-forward CLI usage

Once Cloud is up, the day-to-day commands are:

```
cloud env:pull --environment=production > .env.prod   # mirror env locally
cloud env:push --environment=staging .env.staging     # push env from file
cloud deploy --environment=staging                    # manual deploy (rare)
cloud logs --environment=production --follow         # tail logs
cloud ssh --environment=production                    # shell into the app
cloud db:tunnel --environment=production              # tunnel to Postgres
```

Confirm the exact subcommand names with `cloud --help` — adjust this
runbook if the actual flags differ.

## Follow-ups specific to Laravel Cloud

These don't block first deploy but should be queued:

1. **Add a `/health` endpoint** that pings DB + Redis. Cloud uses `/up`
   by default but a deeper check is useful for your own dashboards.
2. **Switch `LOG_CHANNEL=stderr` to JSON** via a Monolog formatter — makes
   Cloud's log search dramatically more useful.
3. **Wire exception tracking** (Sentry / Flare / Bugsnag).
4. **Add the Stripe webhook route + controller** before configuring Stripe
   webhooks against any environment.
5. **Hibernation** — turn on for `development` + `sandbox`, off for
   `staging` + `production`.
6. **Branch protection** in GitHub on `production` (and arguably
   `staging`): require PR + review, disallow force-push.

## What was deleted in this pivot

Removed in the move from Azure App Service to Laravel Cloud:

- `Dockerfile`, `.dockerignore`, `docker/Caddyfile`, `docker/entrypoint.sh`
  (was for Azure Container Apps, not used by Cloud).
- `nginx.conf`, `startup.sh`, `.htaccess` (App Service-specific runtime
  hacks).
- Repo-root `index.html` (duplicate static landing, shadowed by the
  Laravel route).

What was kept: `docker-compose.yml` (trimmed to Postgres + Redis +
Mailpit for local dev), `.github/workflows/main_vayrepo.yml` (dormant
rollback insurance until Phase J).
