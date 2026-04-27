# Vaytoven Rentals — Platform Architecture

**Version 1.0  |  Companion to: `01_schema.sql`, `02_SRS.docx`, `04_roadmap.md`**

This document describes how Vaytoven Rentals is structured, how data flows through the system, and how the major user journeys are implemented. It's the bridge between the SRS (what we're building) and the schema (where data lives).

---

## 1. System overview

Vaytoven is a four-surface system sharing a single Laravel backend and a single Postgres database.

```
┌────────────────────────────────────────────────────────────────────┐
│                         CLIENT SURFACES                            │
├──────────────────┬──────────────────┬──────────────────┬───────────┤
│   WordPress      │   Web App        │   iOS App        │  Android  │
│   vaytoven.com   │   app.vaytoven   │   (SwiftUI)      │ (Compose) │
│   (marketing)    │   (Blade + Vue)  │                  │           │
└────────┬─────────┴────────┬─────────┴────────┬─────────┴────┬──────┘
         │                  │                  │              │
         │           HTTPS  │                  │              │
         └──────────────────┴──────────────────┴──────────────┘
                                │
                ┌───────────────┴────────────────┐
                │   Cloudflare (CDN, WAF, edge)  │
                └───────────────┬────────────────┘
                                │
                  ┌─────────────┴─────────────┐
                  │   ALB → Laravel (PHP 8.3) │   Stateless ECS Fargate
                  │   api.vaytoven.com/v1     │   Auto-scaling group
                  └──────┬──────────────────┬─┘
                         │                  │
              ┌──────────┴──────┐    ┌──────┴────────┐
              │  PostgreSQL 15  │    │   Redis 7     │
              │  (RDS, primary  │    │  (sessions,   │
              │  + read replica)│    │   queues,     │
              └─────────────────┘    │   rate limits)│
                                     └───────────────┘
                         │
              ┌──────────┴────────────────────────────┐
              │   Async workers (Horizon on ECS)      │
              │   - notifications, payouts, geocoding │
              │   - webhook processing, search index  │
              └───────────────────────────────────────┘

External services:  Stripe · Google Maps · S3/CloudFront · SendGrid · Twilio · IPinfo · Sentry
```

**Why this shape:**

- WordPress is *isolated* on a separate origin so a marketing-site outage never takes down booking.
- The mobile apps and web app speak the same v1 REST API. There is no second API.
- Reads can be served from the read replica when the primary is under load or in maintenance.
- Anything that doesn't have to happen during a request (notifications, geocoding, webhook follow-ups, search reindex) goes to a queue.

---

## 2. Repositories

| Repo | Purpose |
|---|---|
| `vaytoven-platform`     | Laravel monolith — API + admin + host/guest web app. |
| `vaytoven-marketing`    | WordPress site — content, SEO, blog, lead capture. |
| `vaytoven-ios`          | Swift / SwiftUI client. |
| `vaytoven-android`      | Kotlin / Jetpack Compose client. |
| `vaytoven-infra`        | Terraform for AWS resources (VPC, RDS, ECS, S3). |
| `vaytoven-design`       | Figma exports, brand assets, icon set. |

Keep them separate. A monorepo creates incentive to couple things that should stay decoupled.

---

## 3. Front-end pages

### 3.1 WordPress marketing site (`vaytoven.com`)

| Path | Page | Purpose |
|---|---|---|
| `/`                | Home              | Hero, search, featured destinations, social proof, app download. |
| `/become-a-host`   | Host landing      | Earnings calculator, sign-up CTA. |
| `/help`            | Help center       | Articles routed to support tickets. |
| `/blog`            | Blog index        | SEO content. |
| `/legal/terms`     | Terms             | Versioned. |
| `/legal/privacy`   | Privacy           | Versioned. |
| `/about`, `/press` | Static            | Brand pages. |

The search bar on the homepage POSTs to `app.vaytoven.com/search?...` and hands off the user to the application.

### 3.2 Web app (`app.vaytoven.com`)

| Path | Audience | Notes |
|---|---|---|
| `/search`                              | Guest        | List + map view. Filters in URL. |
| `/p/{slug}`                            | Guest        | Listing detail page. |
| `/checkout/{booking-uuid}`             | Guest        | Stripe Elements. |
| `/trips`, `/trips/{uuid}`              | Guest        | Upcoming and past trips. |
| `/wishlists`, `/wishlists/{uuid}`      | Guest        | Saved listings. |
| `/messages`, `/messages/{uuid}`        | Both         | Inbox + thread. |
| `/host/dashboard`                      | Host         | KPIs, today, upcoming. |
| `/host/listings`                       | Host         | Manage listings. |
| `/host/listings/{uuid}/calendar`       | Host         | Pricing + availability. |
| `/host/earnings`, `/host/payouts`      | Host         | Money. |
| `/host/reviews`                        | Host         | Reviews on listings + on host. |
| `/account/profile`                     | Both         | Identity. |
| `/account/security`                    | Both         | 2FA, sessions, login history. |
| `/account/payment-methods`             | Both         | Stripe payment methods. |
| `/admin/...`                           | Admin only   | Behind a separate middleware. |

---

## 4. Back-end modules (Laravel)

Each module is a folder under `app/Domain/<Module>` containing actions, services, validators, jobs, and policies. Controllers stay thin — they orchestrate, they don't decide.

| Module | Responsibility |
|---|---|
| **Auth**          | Registration, login, OAuth, 2FA, password reset, session management. |
| **Users**         | Profile, preferences, roles, host onboarding, identity verification. |
| **Properties**    | Listings, photos, amenities, calendar, pricing rules. |
| **Search**        | Geo + filter queries against PostGIS, faceting, ranking. |
| **Bookings**      | Quote, request, confirm, cancel, refund. Lifecycle state machine. |
| **Payments**      | Stripe charges, refunds, payment method tokens. |
| **Payouts**       | Stripe Connect transfers, schedule, failures. |
| **Messaging**     | Threads, messages, masking rules, notifications. |
| **Reviews**       | Double-blind review windows, aggregation. |
| **Notifications** | Email, SMS, push fan-out. Per-user preferences. |
| **Trust**         | Risk scoring, suspicious-login detection, content moderation. |
| **Admin**         | Moderation queues, audit logs, impersonation, refunds. |
| **Webhooks**      | Stripe, Twilio, third-party callbacks. Idempotent. |

---

## 5. Booking lifecycle

The booking is the heart of the system, so it gets its own state machine. Implement this as an explicit state column with transitions guarded by domain services — never let a controller flip statuses directly.

```
            ┌────────────┐
            │  pending   │  (quote shown to guest)
            └─────┬──────┘
                  │ guest submits
                  ▼
       ┌─────────────────┐
       │   requested     │ ──┐ host has 24h
       └─────┬───────────┘   │
   accept   │                │ expires
            ▼                ▼
       ┌─────────────┐   ┌──────────┐
       │  confirmed  │   │ declined │
       └─────┬───────┘   └──────────┘
             │ check-in passes
             ▼
       ┌─────────────┐
       │  completed  │
       └─────────────┘
                  ▲
   cancellation (any party, before check-in):
        confirmed → cancelled_by_(guest|host) → refund per policy
```

**Concurrency rule:** the database enforces non-overlap on `(property_id, daterange, status IN ('confirmed','completed'))` via a `gist` exclusion constraint (see `01_schema.sql`). A second booking attempt for the same dates will fail at insert time, not at application time.

---

## 6. Payment and payout flow

### 6.1 Booking checkout (Instant Book)

```
1. Client → POST /v1/bookings/quote { property, dates, guests }
                                              │
                                              ▼
   Server → returns immutable quote with price breakdown + idempotency key

2. Client mounts Stripe Elements with the quote's PaymentIntent client_secret

3. User confirms card → Stripe.js calls Stripe directly. Card data never touches our servers.

4. Stripe → webhook payment_intent.succeeded
                ▼
   Server captures funds, marks booking 'confirmed', schedules payout
   for (check-in + 24h), notifies host and guest.
```

### 6.2 Booking checkout (Request to book)

Same as above through step 3, but the PaymentIntent is created with `capture_method: manual`. Funds are *authorized* but not captured. On host accept, we capture. On host decline or 24h timeout, we cancel the intent and the auth is released.

### 6.3 Payouts

Hosts use **Stripe Connect Express**. Each host onboards through Stripe-hosted KYC during their first listing. Payouts are scheduled jobs:

```
Booking.completed → 24h delay → CreatePayoutJob → Stripe Transfer
                                                  → payouts row 'in_transit'
                                                  → webhook 'paid' or 'failed'
```

Failures notify the host with a remediation link to update their bank info.

### 6.4 Money math (always in cents)

```
nightly_rate × nights              = subtotal
subtotal − discount                = discounted_subtotal
discounted_subtotal × 14%          = service_fee_guest         (charged to guest)
discounted_subtotal + cleaning_fee + service_fee_guest + taxes = total
discounted_subtotal × 3%           = service_fee_host          (deducted from payout)
discounted_subtotal − service_fee_host + cleaning_fee          = host_payout
```

Numbers are illustrative for the architecture; actual fee percentages are configured per market.

---

## 7. Host onboarding flow

```
1. User signs up as guest (default).
2. Clicks "Become a host" → updates is_host = true, kicks off the listing wizard.
3. Wizard steps:
     a. Property type + location (geocoded via Google Places)
     b. Capacity (guests, bedrooms, beds, baths)
     c. Amenities + house rules
     d. Photos (min 5, uploaded direct to S3 via pre-signed URLs)
     e. Pricing (base nightly + cleaning fee)
     f. Stripe Connect onboarding (handled by Stripe-hosted form)
     g. Submit for review → status: pending_review
4. Admin reviews via /admin/listings/queue
     - Approve   → status: active, host gets email + push
     - Reject    → status: rejected with reason; host can edit and resubmit
5. First booking on the listing triggers ID verification if not yet completed.
```

---

## 8. Search

Launch with **PostGIS + Postgres only**. Don't reach for OpenSearch or Elasticsearch until p95 measurably suffers.

### 8.1 Query shape

```sql
SELECT p.*, ST_Distance(p.location_geog, :user_point) AS distance_m
FROM properties p
WHERE p.status = 'active'
  AND p.deleted_at IS NULL
  AND ST_DWithin(p.location_geog, :user_point, :radius_meters)
  AND p.max_guests >= :guests
  AND NOT EXISTS (
      SELECT 1 FROM bookings b
      WHERE b.property_id = p.id
        AND b.status IN ('confirmed','completed')
        AND daterange(b.check_in, b.check_out, '[)')
            && daterange(:check_in, :check_out, '[)')
  )
  AND p.base_price_cents BETWEEN :min_price AND :max_price
ORDER BY distance_m
LIMIT 50;
```

The `idx_properties_active_geog` partial GiST index on `location_geog` keeps this query fast at 100k+ active listings.

### 8.2 Map clustering

For zoom levels below city scale, group results server-side using `ST_SnapToGrid` and return cluster metadata to the client:

```
{ "clusters": [{ "lat": 25.78, "lng": -80.21, "count": 47 }, ...] }
```

The client only renders pins for the visible viewport.

---

## 9. Authentication and login security

### 9.1 Tokens

- **Web:** Laravel Sanctum cookie-based session for first-party clients.
- **Mobile:** signed JWT access tokens (15-min TTL) + refresh tokens (30-day TTL, rotated on every use).

### 9.2 The login pipeline

Every login attempt — successful or failed — runs through this pipeline:

```
incoming credentials
        │
        ▼
RateLimit (per-IP and per-account) ──fail──► 429 Too Many Requests
        │
        ▼
Verify credentials ─────────────────fail──► record login_failed, increment failed_login_count
        │
        ▼
RiskScore.calculate(user, ip, ua, device)
        │
        ├── score 0–39 ──► issue token, write login_success
        │
        ├── score 40–69 ─► issue limited token, send email confirmation link,
        │                   write login_success (flagged), require step-up
        │
        └── score 70+   ─► reject, lock account 15 min, write login_failed (suspicious),
                            send security email with timestamp + location + device
```

### 9.3 What goes into a `login_activity` row

Always: user_id, event_type, ip_address, user_agent, occurred_at, device fingerprint.

**Looked up async** (don't block the login on this): country, region, city, postal_code, timezone — derived from the IP via a cached IPinfo lookup. The cache lives in `ip_location_logs` keyed by hashed IP and date. If the lookup fails, the login still succeeds; we just don't have geolocation context.

### 9.4 What we deliberately *don't* do

- We don't fingerprint users across browsers using canvas/WebGL hashing. The OWASP-style "device fingerprint" we use is built from declared user-agent, OS, and a stable mobile install ID — nothing covert.
- We don't store exact GPS from any login event. The only place GPS is captured at all is the mobile app's foreground search, and that data is in-memory only.
- We don't share login_activity rows with third parties. They exist for the user's security, the admin's investigations, and nothing else.

---

## 10. Admin moderation flow

```
Listing pending_review ─► /admin/listings/queue
                             │
              approve ◄──────┴──────► reject (reason required)
                │                          │
                ▼                          ▼
       status: active             status: rejected
       host notified              host notified, can edit + resubmit


Reported content ─► /admin/moderation/queue (unified)
   (reviews, listings, users, messages)
                             │
                ┌────────────┼────────────┐
                ▼            ▼            ▼
            no action    warn user   remove content / ban user
                                         │
                                         ▼
                              admin_audit_logs row written
                              with before/after state
```

Every admin write produces an `admin_audit_logs` row. Reading is logged at INFO level but does not write to that table — otherwise the table grows unboundedly and signal is lost in noise.

---

## 11. Background jobs (Horizon queues)

| Queue       | Examples                                                                 | Why separate |
|-------------|--------------------------------------------------------------------------|--------------|
| `default`   | Email send, push fan-out, geocoding, IP lookup                          | High volume, low criticality |
| `payments`  | Stripe webhook follow-ups, payout creation, refund processing           | Money — must succeed or alert |
| `search`    | Reindex on listing change, denormalize rating averages                  | Bursty, can lag a bit |
| `cleanup`   | Daily login_activity purge, ip_location_logs TTL, soft-delete sweeper   | Slow, low priority |

Each queue has its own concurrency cap so a flood of welcome emails doesn't starve a payment retry.

---

## 12. API authentication and rate limits

| Class | Limit |
|---|---|
| Public read (search, listing detail)         | 60 req/min/IP   |
| Authenticated read                           | 120 req/min/user |
| Authenticated write                          | 30 req/min/user  |
| Booking submit                               | 5 req/min/user   |
| Stripe webhook                               | unlimited (signed) |
| Admin                                        | 240 req/min/admin |

429 responses include a `Retry-After` header. The mobile clients respect it.

---

## 13. Observability

- **Logs:** structured JSON to CloudWatch. Every log line includes `request_id`, `user_id` (if known), and `route`.
- **Metrics:** Datadog dashboards for booking rate, search latency, queue depth, and error rate.
- **Traces:** OpenTelemetry on every API request, sampled at 10% (100% for errors).
- **Alerts:**
  - p95 search latency > 1.5 s for 5 minutes.
  - Stripe webhook queue depth > 500 for 10 minutes.
  - Booking confirm error rate > 2% for 5 minutes.
  - Any 5xx burst above 50/min.

---

## 14. Environments

| Env         | URL                       | Data         | Stripe   |
|-------------|---------------------------|--------------|----------|
| local       | localhost                 | seeded fake  | test     |
| staging     | staging.app.vaytoven.com  | refreshed weekly from prod, PII scrubbed | test |
| production  | app.vaytoven.com          | real         | live     |

Production deploys go: PR → CI → staging auto-deploy → manual promote to production. Database migrations run as a separate, gated step.

---

## 15. What this architecture deliberately leaves out

- **No microservices.** A single Laravel app gets us to 50k bookings/month before this becomes a real bottleneck.
- **No GraphQL.** REST is enough; GraphQL adds caching and authorization complexity we don't need at MVP.
- **No event bus.** Queues are enough. Revisit when we have multiple services.
- **No machine-learning ranking.** Search ranks by distance and rating at launch. Add personalization once we have data.

When any of these become a real problem, we'll know — because metrics will tell us. Until then, "boring" is the feature.
