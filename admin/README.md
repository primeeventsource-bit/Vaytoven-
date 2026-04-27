# `admin/` — Operations console

Internal admin console for member specialists, ops, and trust-and-safety. Currently scoped to the **Members Enquiries queue** (the Managed Listing Program lead-qualification surface). Future surfaces (user moderation, payouts, disputes, etc.) will live here too.

## What's in here

```
admin/
├── index.html      Source — JSX in a <script type="text/babel"> block
├── build.js        Compiles JSX and inlines React; produces prebuilt.html
├── prebuilt.html   Self-contained build artifact
└── package.json    Build dependencies
```

## Run the prebuilt artifact

```bash
open prebuilt.html
```

Out of the box, the admin console runs against an **in-memory mock backend** — 73 realistic mock enquiries, full CRUD via JS state. This makes it usable for design review and demos without needing the Laravel backend running.

## Build from source

```bash
npm install
npm run build
```

## Features

### Members Enquiries queue (scoped to MVP)

- **Sidebar nav** with badge showing open enquiry count
- **5 stats cards** (Open / Last 24h / Last 7d / Unassigned / Flagged spam)
- **Filter bar** — debounced search (name/email/phone/property), status select, source select, program select, include-flagged toggle, refresh, CSV export
- **Sortable table** with status pills color-coded by state, flagged rows tinted red
- **Drawer** with Details / Timeline / Provenance tabs and action buttons: Claim, Mark contacted, Qualify, Onboard as listing, Reject (with required-reason modal), Email, Call
- **Toast notifications** for action confirmations
- **Pagination** at 25 rows per page

### Status pills

| Status | Color |
|--------|-------|
| `new` | Info blue |
| `contacted` | Warn amber |
| `qualified` | Success green |
| `onboarded` | Brand gradient |
| `rejected` | Danger red |
| `unresponsive` / `duplicate` | Muted grey |

## Switching to the production API

The admin console is built so the swap from mock to real backend is one place in the code. Search `index.html` for `PRODUCTION API ADAPTER`. You'll find a `createApi(API_BASE)` factory that returns the same shape as the mock `api` object.

To activate:

1. Set `API_BASE` to your backend URL, e.g. `"https://app.vaytoven.com"`. Leave blank if the admin is served from the same origin as the API.
2. Authenticate. Two options:
   - **Sanctum SPA cookies:** the admin must be served from a domain in your Sanctum stateful list, and the user must have logged in via `POST /api/v1/auth/login`. The adapter sends `credentials: 'include'` automatically.
   - **Bearer token:** call `localStorage.setItem('auth_token', '<token>')` once. The adapter will send it as `Authorization: Bearer ...`.
3. Uncomment the activation block at the bottom of the adapter section:
   ```js
   const productionApi = createApi(API_BASE);
   Object.assign(api, productionApi);  // override every method on `api`
   ```
4. Rebuild: `npm run build`.

The adapter normalizes Laravel's snake_case response fields and pagination wrapper to the camelCase shape the UI expects, so no changes are needed in components.

## Endpoint contract

The adapter calls (and your backend must implement):

| Method | Path | Purpose |
|--------|------|---------|
| `GET` | `/api/v1/admin/members-enquiries` | List with filters |
| `GET` | `/api/v1/admin/members-enquiries/stats` | Aggregate counts |
| `GET` | `/api/v1/admin/members-enquiries/{id}` | Single enquiry |
| `PATCH` | `/api/v1/admin/members-enquiries/{id}` | Update status / assignment / notes |
| `POST` | `/api/v1/admin/members-enquiries/{id}/assign` | Claim or reassign |
| `GET` | `/api/v1/admin/members-enquiries/export` | CSV stream |

All admin endpoints require `auth:sanctum` + the `admin` middleware. Every state-changing call writes to `admin_audit_logs` (see SRS FR-9.7).
