# DocuSign integration — setup & developer handoff

This document describes how the DocuSign eSignature flow is wired into Vaytoven and what an operator/developer needs to do to bring it online.

---

## What's included in this commit

| Concern | File(s) |
|---|---|
| Schema | `database/migrations/2026_05_03_000001_create_contracts_table.php`, `..._create_contract_events_table.php` |
| Models | `app/Models/Contract.php`, `app/Models/ContractEvent.php` |
| DocuSign service layer | `app/Services/DocuSign/{DocuSignClient,EnvelopeService,WebhookVerifier}.php` |
| Admin UI | `app/Http/Controllers/Admin/ContractController.php`, `resources/views/admin/contracts/{index,create,show}.blade.php` |
| Client UI | `app/Http/Controllers/Client/ContractController.php`, `resources/views/client/contracts/{index,show}.blade.php` |
| Webhook | `app/Http/Controllers/Webhooks/DocuSignWebhookController.php` |
| Form Request | `app/Http/Requests/SendContractRequest.php` |
| Routes | `routes/web.php` |
| Config & DI | `config/services.php`, `app/Providers/AppServiceProvider.php`, `bootstrap/app.php` |
| Env template | `.env.example` |
| Layout | `resources/views/layouts/base.blade.php` |
| Composer deps | `composer.json` (added `docusign/esign-client`, `firebase/php-jwt`) |

## What still needs to happen (assumptions this code makes)

This integration is design code — **none of it runs until these prerequisites are met**:

1. **Laravel auth scaffolding** is installed (`composer require laravel/breeze && php artisan breeze:install`). The routes and controllers reference `auth()->user()` and the `auth` middleware. The `App\Models\User` class also needs to exist for `Contract::user()` relationship.
2. **An `admin` middleware / role check** must gate the `/admin/*` routes — this commit does NOT include role logic. Easiest stub: a middleware that checks `auth()->user()->is_admin === true`.
3. **The `users` table must exist** before running these migrations. Once auth scaffolding is in place, run all migrations together.
4. **A real DocuSign account** with API access (see "DocuSign account setup" below).
5. **A publicly reachable, HTTPS Vaytoven URL** so DocuSign Connect can deliver webhooks to `/webhooks/docusign`.

---

## DocuSign account setup (one-time, by an operator)

### Tier required

DocuSign **Connect webhooks** require:
- A paid DocuSign plan that includes API/Connect (Business Pro tier or eSignature API plan), or
- A free Developer Sandbox account at <https://developers.docusign.com/> for testing.

### 1. Create a Developer Sandbox

Sign up at <https://developers.docusign.com/> → confirm email → log in to the Developer Sandbox.

### 2. Create an Integration Key

1. Sandbox admin → **Apps and Keys**
2. **Add App and Integration Key**
3. Name: `Vaytoven`
4. Copy the **Integration Key (User ID-style UUID)** — this becomes `DOCUSIGN_INTEGRATION_KEY`.

### 3. Generate an RSA keypair

In the same screen:

1. **Service Integration** → **Generate RSA**
2. Save the **private key** somewhere safe — paste it as `DOCUSIGN_PRIVATE_KEY` (with `\n` for newlines) **or** save to a file and point `DOCUSIGN_PRIVATE_KEY_PATH` to it.
3. The public half stays registered on the Integration Key — DocuSign keeps it.

### 4. Note the User ID and Account ID

- **User ID** → top-right account menu → "My Profile" → API Username (a UUID). This becomes `DOCUSIGN_USER_ID`.
- **Account ID** → Apps and Keys page lists it under the integration. This becomes `DOCUSIGN_ACCOUNT_ID`.

### 5. Grant impersonation consent (one-time)

Visit the URL below in a browser and sign in as the user the integration will impersonate (typically the same admin user who created the Integration Key):

```
https://account-d.docusign.com/oauth/auth
  ?response_type=code
  &scope=signature%20impersonation
  &client_id={DOCUSIGN_INTEGRATION_KEY}
  &redirect_uri=https://vaytoven.com/oauth/docusign/callback
```

(`redirect_uri` must match a URI registered on the Integration Key; the actual page contents don't matter — DocuSign just needs the consent screen to be acknowledged once.)

### 6. Configure Connect (webhooks)

1. Sandbox admin → **Connect** → **Add Configuration** → **Custom**
2. **Name:** `Vaytoven`
3. **URL to publish to:** `https://your-vaytoven-host.com/webhooks/docusign`
4. **Format:** JSON
5. **Trigger events:** at minimum
   - Envelope Sent / Delivered / Completed / Declined / Voided
   - Recipient Sent / Delivered / Completed / Declined / Authentication Failed
6. **Include HMAC Signature** → enabled. Copy the key into `.env` as `DOCUSIGN_HMAC_KEYS`. (When rotating, you can have up to 10 active keys; pipe-separate them: `KEY1|KEY2`.)
7. **Sign Message Body Using a Secret** → recommended.

### 7. Switch to production

When you go live, change two env vars:

```
DOCUSIGN_OAUTH_BASE=https://account.docusign.com
DOCUSIGN_API_BASE=https://www.docusign.net
```

Repeat steps 2–6 against the production DocuSign account.

---

## Composer dependencies

```bash
composer require docusign/esign-client firebase/php-jwt
```

These are also declared in `composer.json` so `composer install` on deploy will pull them in automatically.

---

## Environment variables

See `.env.example` for the full list. Minimum to make this work:

```
DOCUSIGN_OAUTH_BASE=https://account-d.docusign.com
DOCUSIGN_API_BASE=https://demo.docusign.net
DOCUSIGN_INTEGRATION_KEY={integration_key_uuid}
DOCUSIGN_USER_ID={api_user_uuid}
DOCUSIGN_ACCOUNT_ID={account_id}
DOCUSIGN_PRIVATE_KEY_PATH=/secure/path/to/docusign-private.pem
DOCUSIGN_HMAC_KEYS={connect_hmac_key}
```

---

## Running the migrations

After auth scaffolding is installed (so `users` table exists):

```bash
php artisan migrate
```

This creates `contracts` and `contract_events`.

---

## Sending a test contract (admin flow)

1. Sign in as a staff user.
2. Go to `/admin/contracts/create`.
3. Fill in client name + email, pick a contract type, give it a title.
4. Either:
   - Paste a DocuSign **Template ID** (recommended for repeatable agreements), or
   - **Upload a PDF** (DocuSign places one signature tab on page 1).
5. Submit. The system creates an envelope, marks the contract `sent`, and shows the detail page.

The client receives a DocuSign email by default. If you want them to sign embedded inside Vaytoven instead, the `recipientViewUrl` flow in `EnvelopeService` is already wired — `Client\ContractController::sign()` redirects them into it.

## Client signing flow

1. Client signs in to Vaytoven.
2. Goes to `/account/contracts`.
3. Clicks **Review & sign** on a pending contract.
4. Vaytoven calls `EnvelopeService::recipientViewUrl()` to get a one-time DocuSign URL and redirects.
5. Client signs in DocuSign's UI; DocuSign returns them to `/account/contracts/{id}?event=signed`.
6. **Webhook** fires (separately, server-to-server) — updates the contract row, downloads the signed PDF + certificate, persists them.
7. On reload, the client sees **Download signed PDF** instead of **Review & sign**.

---

## What the webhook captures

Each Connect event creates a `contract_events` row recording:

- `event_type` (sent, delivered, viewed, signed, completed, declined, voided, authentication_failed, …)
- `occurred_at` (timestamp from DocuSign)
- `recipient_id`, `recipient_email`
- `ip_address` (signer's IP from DocuSign payload)
- `user_agent` (signer's device/browser string)
- `raw_payload` (full JSON, kept for forensics)

The `contracts` row is updated with the latest status and the most recent signer IP/UA. Per-event history lives in `contract_events`.

---

## Schema (mapping back to the original spec)

| Spec field | Where it lives |
|---|---|
| Client name | `contracts.client_name` |
| Email | `contracts.client_email` |
| Phone | `contracts.client_phone` |
| User ID | `contracts.user_id` |
| Contract ID | `contracts.id` |
| DocuSign envelope ID | `contracts.envelope_id` |
| Contract sent date/time | `contracts.sent_at` (and `contract_events` for `event_type='sent'`) |
| Contract viewed date/time | `contracts.viewed_at` (and `contract_events`) |
| Contract signed date/time | `contracts.signed_at` (and `contract_events`) |
| Signer IP address | `contracts.last_signer_ip` (most recent) + per-event in `contract_events.ip_address` |
| Signer device / browser / user agent | `contracts.last_signer_user_agent` + per-event in `contract_events.user_agent` |
| Website or app source | `contracts.source` (`web` / `app` / `admin`) |
| Terms accepted timestamp | `contracts.terms_accepted_at` |
| Payment / invoice ID | `contracts.payment_id` |
| Signed PDF | `contracts.signed_pdf_path` (Storage::disk('local')) |
| Certificate of signing | `contracts.certificate_pdf_path` |

---

## Open questions to confirm before going live

1. **Document storage:** PDFs currently land on the `local` filesystem disk. For production, switch to Azure Blob / S3 (`config/filesystems.php`) so PDFs survive container redeploys.
2. **Email notifications:** Do you want a Vaytoven-branded "your contract is ready" email separate from DocuSign's default? If yes, dispatch a Mailable from `Admin\ContractController::store()` after `$envelope->send()`.
3. **Multi-signer:** Current schema supports one primary signer. If contracts will be countersigned by Vaytoven staff (host listing agreements typically are), add a `contract_signers` table with `recipient_id`, `name`, `email`, `routing_order`.
4. **Retention policy:** How long do signed PDFs and certificates stay accessible to clients? Add a scheduled job to soft-delete or archive after N years.
5. **Reminders:** DocuSign supports reminders + expirations natively — set them on the envelope creation in `EnvelopeService::send()` if needed.
6. **GDPR:** `contract_events.raw_payload` includes signer PII. If you operate in the EU, add a redaction job and a data-subject-export endpoint.

---

## Voice / brand reminder

User-facing copy in these views uses **vacation property / vacation club / member / points-based**. The T-word ("timeshare") is forbidden anywhere a customer can see — see [`README.md`](../README.md) "Language Rule".
