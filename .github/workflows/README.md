# CI workflows

## Deployment is not done here

**Vaytoven deploys through Laravel Cloud push-to-deploy, not through GitHub
Actions.** Pushing a branch is the deploy: Laravel Cloud watches the repository
and builds the matching environment itself (`usesPushToDeploy: true` on all
three environments).

| Branch       | Laravel Cloud environment | URL                                          |
|--------------|---------------------------|----------------------------------------------|
| `main`       | main                      | https://v-app-dev-main-oyo1n9.laravel.cloud       |
| `production` | production                | https://v-app-dev-production-iddl1a.laravel.cloud |
| `development`| development               | https://v-app-dev-development-67mji2.laravel.cloud |

All three are environments of the single `v-app-dev` application. The former
`v-app-production` and `v-app-sandbox` applications were deleted during
consolidation — their hostnames now return Cloudflare 530, so do not reference
them anywhere.

Migrations run automatically as each environment's deploy command. Seeders do
**not** — `RbacSeeder`, `SettingsSeeder` and `LegalDocumentSeeder` are run
manually per environment via `cloud command:run`, deliberately, because
`LegalDocumentSeeder` mints a new terms version and forces every user to
re-accept.

There was previously a `main_vayrepo.yml` workflow that deployed to an Azure
Web App. It was a leftover from before the Laravel Cloud migration, had been
failing on every push to `main`, and deployed to a target nobody uses. It was
removed on 2026-08-11. **Do not add a deploy workflow here** — if a deploy is
not happening, the problem is in Laravel Cloud, not in Actions.

## What does run here

`test.yml`:

- **PHPUnit** — every push to `main` / `development` / `production`, and every
  PR. Fast, in-memory, no external dependencies.
- **Playwright E2E** — pushes to `main` and `production` only, because it hits
  the deployed URL and needs a reachable environment. `development` is excluded
  because its environment has no database attached, so `/health` never returns
  green. Add it back here once one is attached.

### Caveat: E2E writes to the environment it tests

The registration happy-path creates a real account (`e2e+<timestamp>@vaytoven.test`)
on the target environment every run. Those accumulate. Point E2E at a
disposable environment before treating user counts on `main` as real.
