# Contributing to Vaytoven Rentals

Internal team only at this stage. This guide covers the workflow for engineers, designers, and ops who land in the repo.

## Branching

```
main                       Always deployable. Protected branch. Merges via PR only.
release/yyyy-mm-dd         Cut from main when preparing a production release.
feat/<short-handle>        Feature branches.
fix/<short-handle>         Bugfix branches.
chore/<short-handle>       Tooling, deps, or refactor with no behavior change.
docs/<short-handle>        Documentation-only changes.
```

## Commit messages

Use conventional commits. Subject in lowercase, present tense, no period.

```
feat(admin): add csv export to members enquiries queue
fix(backend): correct stripe webhook idempotency check
docs(srs): clarify FR-9.8 banned-term scope
chore(deps): bump laravel from 11.27 to 11.28
```

Body is optional. If you include one, wrap at 72 cols and explain the *why*, not the *what* — the diff already shows the what.

## Pull requests

- **One concern per PR.** If you find yourself writing "and also" in the description, split it.
- **Link to the FR-ID** in `docs/SRS.md` when the change implements a requirement, e.g. "Implements FR-9.5."
- **Tests are not optional** for backend logic. Add a feature test covering happy path + at least one error path.
- **Screenshot or video** for any UI change. Drag into the PR description; GitHub will host it.
- **Self-review before requesting review.** If a reviewer's first comment would be "did you mean to leave this `console.log` in?", you didn't self-review.

PRs are squash-merged. The squash commit message becomes the merge commit on `main`, so write the title as if it's the only message anyone will ever see.

## Code style

### PHP / Laravel (backend/)

- Follow [PSR-12](https://www.php-fig.org/psr/psr-12/). Run `./vendor/bin/pint` before committing.
- Eloquent models stay thin — relationships, scopes, casts, accessors. Business logic lives in `app/Services/*`.
- Form Requests handle validation and authorization; controllers stay slim.
- Controllers return `Resource` classes, never raw arrays or models.
- Money is stored as integer cents, ALWAYS. Never `decimal`, never `float`.
- Migrations are forward-only — never modify a shipped migration; write a new one.

### React (app/, admin/)

- Functional components with hooks only. No classes.
- Use the existing `AppProvider` / context pattern; no Redux, no Zustand, no MobX.
- CSS is in the `<style>` block at the top of `index.html`. Use the existing CSS variables (search for `--brand-magenta`, `--ink`, etc.) instead of hardcoding colors.
- Don't add a build framework (Vite, Next, CRA). The repo's whole charm is that `prebuilt.html` is one file you can email to a stakeholder.
- When you wire to the real API, do it through the adapter pattern — see `admin/index.html`'s `createApi()`.

### SQL (docs/schema.sql)

- All money columns end in `_cents` and are `BIGINT NOT NULL`.
- Boolean columns are named affirmatively: `is_active`, never `not_inactive`.
- Foreign keys use `ON DELETE` explicitly. Never default.
- Every table has `created_at` and `updated_at` `TIMESTAMPTZ NOT NULL`. Soft-deletable tables also have `deleted_at TIMESTAMPTZ NULL`.

## Reviewing PRs

If you're reviewing:

- Read the code, not the description.
- Run it locally if it touches anything user-facing.
- "LGTM" is fine for a one-line typo fix. For anything substantive, leave at least one concrete observation.
- Block on correctness, security, and SRS compliance. Suggest on style.

## Secrets and `.env`

NEVER commit a `.env` file. The repo has `.env.example` files in each surface that show the required variables. If you add a new env var, add it to `.env.example` in the same PR with a real (non-secret) example value or `<your-token-here>` placeholder.

If you accidentally commit a secret, rotate it immediately. The git history must be cleaned with `git filter-repo` and force-pushed by a maintainer.

## Documentation

If your PR changes behavior described in `docs/SRS.md` or `docs/schema.sql`, update those docs in the same PR. Out-of-date specs are worse than no specs.

If you're adding a new section to the SRS, follow the existing FR numbering convention (`FR-X.Y`) and update the table-of-contents in `docs/SRS.md`.

## Questions

- Architecture questions → check `docs/architecture.md` first, then ask in `#engineering`.
- Product questions → check `docs/SRS.md` first, then ask in `#product`.
- Trust-and-safety / legal-review questions → never make a unilateral call; route through counsel.

Welcome.
