VAYTOVEN RENTALS  ·  SRS v1.1

**VAYTOVEN RENTALS**

Software Requirements Specification

Version 1.1

Prepared for Vaytoven Rentals LLC

*Last updated: April 2026*

*CONFIDENTIAL — Distribute only to engineering, product, and legal counsel.*

# 1. Introduction

## 1.1 Purpose

This Software Requirements Specification (SRS) defines the functional and non-functional requirements for Vaytoven Rentals — a two-sided vacation rental marketplace that connects hosts with travelers. This document is the source of truth for the engineering team during MVP development and is intended to be precise enough to estimate, build, and test against.

## 1.2 Scope

Vaytoven Rentals is delivered as four coordinated surfaces:

- A WordPress marketing site at the apex domain (vaytoven.com) for SEO, content, and lead capture.

- A PHP/Laravel monolith serving the web application (app.vaytoven.com) and a versioned REST API.

- Native mobile apps for iOS and Android consuming the same REST API.

- An internal admin console for moderation, payouts, disputes, and security review.

The platform serves three distinct audiences: travelers (guests), property hosts (self-serve), and vacation property members participating in the sales-assisted Managed Listing Program (see §3.9).

Out of scope for v1.0: experiences/activities marketplace, long-term rentals (>30 nights), corporate travel programs, and a public host API.

## 1.3 Definitions

| **Term** | **Definition** |
| --- | --- |
| Guest | A user who books a property. |
| Host | A user who lists one or more properties. |
| Listing | A single property published by a host. |
| Booking | A reservation tied to a guest, host, listing, and date range. |
| Payout | A transfer of funds from Vaytoven to a host's connected Stripe account. |
| Service fee | The platform's commission, charged on every booking. |
| PII | Personally identifiable information. |
| MFA / 2FA | Multi-factor / two-factor authentication. |

## 1.4 References

- Stripe Connect documentation — stripe.com/docs/connect

- OWASP ASVS 4.0 — Application Security Verification Standard

- GDPR (Regulation EU 2016/679) and CCPA (Cal. Civ. Code §1798.100)

- Apple App Store Review Guidelines and Google Play Developer Policies

- Companion documents: 01_schema.sql, 03_architecture.md, 04_roadmap.md

# 2. Overall Description

## 2.1 Product Perspective

Vaytoven is a new marketplace built from scratch. It is not a rebrand or an acquisition. The product competes directly with Airbnb, Vrbo, and Booking.com in the short-term rental category. Its differentiators at launch are (a) curated supply, (b) faster host payouts, and (c) trust-first defaults including verified hosts and stricter listing review.

## 2.2 User Classes

| **Role** | **Primary capabilities** |
| --- | --- |
| Guest | Search listings, book stays, message hosts, leave reviews, manage payment methods. |
| Host | List properties, manage calendar and pricing, accept bookings, message guests, receive payouts. |
| Admin | Moderate listings and users, resolve disputes, view security logs, issue refunds. |
| Super Admin | All admin powers plus role management, financial reconciliation, system configuration. |

A single user can hold multiple roles (e.g. a host who also books trips). Roles are additive.

## 2.3 Operating Environment

- Web app: latest two major versions of Chrome, Safari, Firefox, and Edge.

- Mobile: iOS 16+ on iPhone 11 and newer; Android 11+ on devices with 3 GB RAM or more.

- Hosting: AWS (us-east-1 primary, with read replicas in eu-west-1 once EU traffic justifies it).

- Runtime: PHP 8.3 on Laravel 11; PostgreSQL 15; Redis 7; Node 20 for build tooling.

## 2.4 Assumptions and Dependencies

- Stripe Connect (Express accounts) is available in the launch markets.

- A Google Maps Platform billing account is provisioned before week 4 of development.

- A KYC/identity verification vendor is selected before host verification ships (Persona or Stripe Identity).

- Apple Developer and Google Play Developer accounts are registered under the legal entity Vaytoven Rentals LLC.

- A privacy counsel review is completed before public beta — specifically reviewing the security-logging design described in §6.

# 3. System Features and Functional Requirements

Each requirement is tagged with an ID (FR-X.Y), a priority (Must / Should / Could), and an MVP phase. Acceptance criteria are listed where they aren't obvious.

## 3.1 Authentication and Account Management

| **ID** | **Requirement** | **Priority** | **Phase** |
| --- | --- | --- | --- |
| FR-1.1 | Users can sign up with email + password. | Must | MVP |
| FR-1.2 | Users can sign up with Apple ID, Google, or Facebook OAuth. | Should | MVP |
| FR-1.3 | Email verification is required before booking or listing. | Must | MVP |
| FR-1.4 | Phone verification (SMS OTP) is required before listing. | Must | MVP |
| FR-1.5 | Users can enable TOTP-based 2FA. Backup codes are generated. | Must | MVP |
| FR-1.6 | Passwords use Argon2id (or bcrypt cost 12+) and a minimum length of 10 chars. | Must | MVP |
| FR-1.7 | After 5 failed logins in 15 minutes, the account is temporarily locked. | Must | MVP |
| FR-1.8 | Password reset via signed, single-use, 30-min email link. | Must | MVP |
| FR-1.9 | Users can list active sessions and remotely revoke any session. | Should | MVP |
| FR-1.10 | Users can permanently delete their account, triggering a 30-day soft-delete period. | Must | MVP |

## 3.2 Property Listings (Host)

| **ID** | **Requirement** | **Priority** | **Phase** |
| --- | --- | --- | --- |
| FR-2.1 | A host can create a draft listing with type, location, capacity, and photos. | Must | MVP |
| FR-2.2 | A listing has a minimum of 5 photos before it can be submitted for review. | Must | MVP |
| FR-2.3 | Listings enter `pending_review` on submit; admin approval moves them to `active`. | Must | MVP |
| FR-2.4 | A host can set a base nightly price, cleaning fee, and weekly/monthly discounts. | Must | MVP |
| FR-2.5 | A host can mark specific dates unavailable or set per-night price overrides. | Must | MVP |
| FR-2.6 | A host can sync availability from external iCal feeds (e.g. Airbnb, Vrbo). | Should | P2 |
| FR-2.7 | A host can pause a listing without losing data or reviews. | Must | MVP |
| FR-2.8 | A host can offer Instant Book or require booking requests. | Should | MVP |
| FR-2.9 | Listings show denormalized `rating_avg` and `rating_count` updated nightly. | Must | MVP |

## 3.3 Search and Discovery (Guest)

| **ID** | **Requirement** | **Priority** | **Phase** |
| --- | --- | --- | --- |
| FR-3.1 | Guests can search by location, dates, and guest count. | Must | MVP |
| FR-3.2 | Search supports filters: price range, property type, bedrooms, amenities, instant book. | Must | MVP |
| FR-3.3 | Search results show a paginated list and an interactive map view. | Must | MVP |
| FR-3.4 | Map markers cluster at zoom levels below city scale. | Should | MVP |
| FR-3.5 | A logged-in guest can save a listing to a wishlist with a single tap. | Must | MVP |
| FR-3.6 | Search responses are p95 < 800 ms for queries inside a single bbox. | Must | MVP |
| FR-3.7 | Guests can save a search and receive alerts when matching listings appear. | Could | P2 |

## 3.4 Booking and Payments

| **ID** | **Requirement** | **Priority** | **Phase** |
| --- | --- | --- | --- |
| FR-4.1 | Booking submits an authorization on the guest's payment method. | Must | MVP |
| FR-4.2 | For Instant Book listings, the charge is captured on confirmation. | Must | MVP |
| FR-4.3 | For request-to-book, the host has 24 h to accept or the request expires and the auth is released. | Must | MVP |
| FR-4.4 | Booking totals snapshot all fees at the time of booking. Later fee changes do not affect existing bookings. | Must | MVP |
| FR-4.5 | The system never allows two confirmed bookings for the same listing on overlapping dates (enforced at the database level). | Must | MVP |
| FR-4.6 | Cancellation policies (Flexible/Moderate/Strict) drive refund calculations automatically. | Must | MVP |
| FR-4.7 | Refunds flow to the original payment method via Stripe. | Must | MVP |
| FR-4.8 | Host payouts are released 24 h after guest check-in via Stripe Connect. | Must | MVP |
| FR-4.9 | All Stripe webhooks are idempotently processed and logged. | Must | MVP |
| FR-4.10 | Failed captures put the booking into `payment_failed` and notify both parties. | Must | MVP |

## 3.5 Messaging

| **ID** | **Requirement** | **Priority** | **Phase** |
| --- | --- | --- | --- |
| FR-5.1 | Guests can message a host before booking. | Must | MVP |
| FR-5.2 | Messaging is rate-limited (max 10 new threads per hour per user). | Must | MVP |
| FR-5.3 | Phone numbers and external URLs are masked in pre-booking messages to prevent off-platform booking. | Should | MVP |
| FR-5.4 | Attachments are virus-scanned before delivery. | Should | P2 |
| FR-5.5 | Each booking has its own thread that retains messages even if the booking is cancelled. | Must | MVP |

## 3.6 Reviews

| **ID** | **Requirement** | **Priority** | **Phase** |
| --- | --- | --- | --- |
| FR-6.1 | Both parties may submit a review within 14 days of checkout. | Must | MVP |
| FR-6.2 | Reviews are double-blind: neither party sees the other's review until both are submitted or 14 days pass. | Must | MVP |
| FR-6.3 | Reviews include an overall 1–5 rating and six sub-ratings. | Must | MVP |
| FR-6.4 | Users can flag reviews; flagged reviews enter the moderation queue. | Must | MVP |

## 3.7 Admin and Moderation

| **ID** | **Requirement** | **Priority** | **Phase** |
| --- | --- | --- | --- |
| FR-7.1 | Admins can list, search, and impersonate users (impersonation is logged and time-boxed). | Must | MVP |
| FR-7.2 | Admins approve or reject pending listings with reason codes. | Must | MVP |
| FR-7.3 | Admins can issue full or partial refunds outside cancellation policies. | Must | MVP |
| FR-7.4 | All admin write actions are recorded in admin_audit_logs with before/after state. | Must | MVP |
| FR-7.5 | A unified moderation queue surfaces flagged reviews, reported listings, and disputes. | Must | MVP |
| FR-7.6 | Admins can view a user's recent login activity and active sessions. | Must | MVP |

## 3.8 Notifications

- Email is the default channel for booking events. SMS is opt-in for booking-day reminders only.

- Push notifications are sent via APNs (iOS) and FCM (Android), gated by per-type user preferences.

- In-app notifications are persisted for 90 days, then archived.

## 3.9 Managed Listing Program (Vacation Property Members)

The Managed Listing Program is a parallel inventory channel for owners of points-based vacation properties (members of major resort networks and exchange programs). Unlike the standard self-serve host flow, the Managed Listing Program is a sales-assisted, lead-qualified onboarding: members express interest, a member specialist contacts them within one business day, and qualified members have their unused points-based weeks converted into managed listings on the platform. Public-facing copy avoids industry jargon and refers to "vacation properties" or "points-based ownership."

| **ID** | **Requirement** | **Priority** | **Phase** |
| --- | --- | --- | --- |
| FR-9.1 | The marketing site exposes a "Managed Listing Program" section with a CTA that opens an enquiry modal capturing: name, email, phone, vacation club / program, property name and location, annual points balance (optional), best time to call (optional), free-text notes (optional), and explicit consent. | Must | MVP |
| FR-9.2 | The mobile app and web app expose the same enquiry entry-point as a contextual banner — on the search results page (traveler context) and on the host dashboard (host context) — both opening the same modal. | Must | MVP |
| FR-9.3 | Submitted enquiries are persisted to the `members_enquiries` table with `status='new'`, `source` set to one of `website` / `app_search` / `app_host`, and `consent_at` set to the submission timestamp. | Must | MVP |
| FR-9.4 | The system fans out a new-enquiry notification to (a) the assigned member-specialist channel in Slack and (b) a confirmation email to the enquirer's address, both within 60 seconds of submission. | Must | MVP |
| FR-9.5 | The admin console provides a Members Enquiries queue with filters by `status`, `assigned_to`, `program`, and `created_at` range, plus row-level actions to assign, mark contacted, qualify, reject, or mark unresponsive. | Must | MVP |
| FR-9.6 | A member specialist can convert a qualified enquiry into a managed listing, which creates a row in `properties` with the specialist as the host-of-record proxy and writes back the new property's UUID into `members_enquiries.converted_property_id`. | Must | MVP |
| FR-9.7 | All status transitions on `members_enquiries` are recorded in `admin_audit_logs` with before/after state and acting user, mirroring FR-7.4. | Must | MVP |
| FR-9.8 | The enquiry form must NEVER reference the legal industry term "timeshare" in user-facing copy or analytics events; refer to "vacation property," "points-based ownership," or "vacation club" instead. | Must | MVP |
| FR-9.9 | The enquiry endpoint is rate-limited to 10 submissions per IP per hour and applies basic spam scoring; submissions failing the score threshold are written with `status='new'` but flagged in `notes` and excluded from the default queue view. | Should | MVP |
| FR-9.10 | Members specialists can export the queue (filtered or full) to CSV for offline review; the export is logged in `admin_audit_logs`. | Should | Phase 2 |
| FR-9.11 | The Managed Listing Program program-name field is sourced from a curated list (Marriott Vacation Club, Hilton Grand Vacations, Disney Vacation Club, Wyndham Destinations, Hyatt Residence Club, Diamond Resorts, Worldmark by Wyndham, Bluegreen Vacations, Westgate, RCI Points, Interval International, Other / Independent). The list is configurable via admin without a code deploy. | Should | Phase 2 |

### 3.9.1 Conversion Path

When a managed listing is created from an enquiry, it must:

- Inherit standard `properties` fields with payout configured to a Vaytoven-controlled escrow Stripe account, with a separate revenue-sharing record stored against the enquiry's specialist for downstream member payment.

- Be flagged with `properties.listing_source='managed'` so it can be excluded or boosted in search ranking experiments without affecting standard host listings.

- Honor a separate cancellation policy default (`moderate`) with the option for the specialist to override per listing.

### 3.9.2 Compliance

The Managed Listing Program operates under separate disclosures from the standard host program. The legal review for the program (terms of service, member agreement, payout schedule, escrow handling, and revenue-share disclosures) is a Phase-2 deliverable and must clear counsel before the first managed listing publishes. The MVP enquiry-capture flow is permitted to launch independently because it commits the platform only to a follow-up call, not to a binding listing agreement.

# 4. External Interface Requirements

## 4.1 REST API

All client-server traffic uses HTTPS over a versioned JSON API hosted at api.vaytoven.com/v1. Authentication uses Sanctum-issued bearer tokens (web) or signed JWTs with refresh tokens (mobile).

Selected endpoints (full list in 03_architecture.md):

POST   /v1/auth/register
POST   /v1/auth/login
POST   /v1/auth/refresh
POST   /v1/auth/logout
GET    /v1/properties              # search
GET    /v1/properties/{uuid}
POST   /v1/properties              # host: create
PATCH  /v1/properties/{uuid}
POST   /v1/properties/{uuid}/availability
POST   /v1/bookings                # quote + create
GET    /v1/bookings/{uuid}
POST   /v1/bookings/{uuid}/cancel
POST   /v1/bookings/{uuid}/confirm # host action
GET    /v1/threads
POST   /v1/threads/{uuid}/messages
POST   /v1/reviews
POST   /v1/wishlists/{uuid}/items
GET    /v1/me/security/sessions
DELETE /v1/me/security/sessions/{uuid}
POST   /v1/webhooks/stripe         # signed

## 4.2 Third-Party Integrations

| **Integration** | **Purpose** | **Notes** |
| --- | --- | --- |
| Stripe + Stripe Connect | Payments, payouts, KYC for hosts | Webhooks signed and verified. |
| Google Maps Platform | Geocoding, map tiles, place autocomplete | Restrict API key by domain and HTTP referrer. |
| AWS S3 + CloudFront | Photo storage and CDN delivery | All uploads go through pre-signed URLs. |
| SendGrid (or Postmark) | Transactional email | DKIM and SPF configured for vaytoven.com. |
| Twilio | SMS OTP and reminders | Toll-free verified for US delivery. |
| IPinfo or MaxMind | IP geolocation | Cached server-side; never exposed to clients. |
| Persona (or Stripe Identity) | Government-ID verification for hosts | Required before payouts above threshold. |
| Sentry | Error tracking | PII scrubbed before send. |
| Datadog or Grafana Cloud | APM and infrastructure metrics |  |

## 4.3 Mobile App Surfaces

- iOS: Swift + SwiftUI, distributed via App Store Connect. Minimum target: iOS 16.

- Android: Kotlin + Jetpack Compose, distributed via Google Play Console. Minimum SDK: 30 (Android 11).

- Both apps consume the v1 REST API. No platform-specific business logic — all rules live on the server.

# 5. Non-Functional Requirements

## 5.1 Performance

- API p95 latency < 400 ms for read endpoints, < 800 ms for search.

- Booking endpoint p95 < 1.5 s including Stripe round-trip.

- Web Largest Contentful Paint (LCP) < 2.5 s on a Moto G4-class device on 4G.

- Mobile cold start (TTI) < 3.0 s on midrange devices.

## 5.2 Scalability

- Horizontally scale Laravel on stateless ECS Fargate tasks behind an ALB.

- PostgreSQL on RDS with one primary and one read replica at launch.

- Redis (ElastiCache) for sessions, rate limits, queue (Horizon), and short-lived caches.

- Background workers run on dedicated Horizon-managed queues with separate scaling rules.

- Photo uploads bypass the API: client → S3 directly via pre-signed URL.

## 5.3 Availability

- Target SLO: 99.9% monthly availability for the booking and search paths.

- Read endpoints fall back to the read replica during primary maintenance.

- Stripe webhook handler is idempotent; replays during incidents do not double-charge.

## 5.4 Security

- OWASP Top 10 compliance verified by an external pentest before public beta.

- Secrets in AWS Secrets Manager. No secrets in code or environment files committed to git.

- All traffic TLS 1.2+. HSTS preloaded for vaytoven.com.

- CSP, X-Content-Type-Options, X-Frame-Options, Referrer-Policy, and Permissions-Policy headers set on every response.

- Rate limiting at the edge (Cloudflare) and per-user at the application layer.

- PII is encrypted at rest using AWS-managed KMS keys.

- PCI-DSS scope is reduced to SAQ-A by never touching card numbers — Stripe Elements only.

## 5.5 Usability and Accessibility

- WCAG 2.1 AA compliance is the design baseline.

- All interactive elements meet a 4.5:1 contrast ratio.

- Mobile apps support Dynamic Type (iOS) and Font Scale (Android) up to 200%.

- All forms support keyboard-only navigation and screen readers (VoiceOver, TalkBack).

## 5.6 Internationalization

- UI strings are externalized to translation files. English at launch.

- All currency is stored in cents with an explicit ISO-4217 code.

- All timestamps are stored as UTC and rendered in the property's local timezone.

# 6. Privacy, Logging, and Compliance

This section is the policy layer behind the login_activity, ip_location_logs, and admin_audit_logs tables. It is binding on every code path that writes to or reads from those tables.

## 6.1 What we collect on login

- User ID, event type, timestamp.

- IP address (IPv4 or IPv6).

- Approximate location derived from IP — country, region, city, postal code, and timezone.

- Device characteristics: type, OS, browser, mobile install ID.

- Risk score and reason if flagged as suspicious (impossible-travel, new country, Tor exit node, etc.).

## 6.2 What we do NOT collect

- **Exact GPS coordinates **are never collected from the login event itself. The mobile apps may request foreground location with explicit permission for property search proximity, but that data is used in-memory only and is not persisted to login_activity.

- **Biometric data **is never sent to our servers. On-device Face ID and fingerprint unlock are handled entirely by iOS Keychain and Android Keystore.

- Card numbers, CVV, or full bank account numbers — these stay in Stripe.

## 6.3 Consent flow

First-party telemetry is described in the Privacy Policy and Terms of Service. The user must affirmatively accept both before completing signup. The accepted version is stored in the users table (privacy_policy_version, tos_version).

Marketing communications and analytics cookies are gated by a separate consent banner that must be re-confirmed when policy versions change.

## 6.4 Retention and purge

| **Data class** | **Retention** | **Purge mechanism** |
| --- | --- | --- |
| login_activity | 365 days | Daily scheduled job deletes rows older than the retention window. |
| ip_location_logs | 180 days (cache) | TTL field; expired rows purged on cron. |
| user_sessions | Until expiry or revocation | Hard delete after 30 days post-expiry. |
| admin_audit_logs | 7 years | Required for financial dispute defense; never purged automatically. |
| Soft-deleted users | 30 days | After 30 days, PII is anonymized; bookings retained for accounting. |
| Messages | 5 years post-booking | Required for trust-and-safety investigations. |

## 6.5 Subject rights (GDPR/CCPA)

- Right of access: a user can download a JSON export of all their data within 30 days of request.

- Right to deletion: account deletion triggers a 30-day grace period, then anonymization. Records required for tax, AML, or active disputes are exempt and retained per applicable law.

- Right to object: marketing consent can be withdrawn from the account settings page at any time.

- Data Processing Agreements are in place with Stripe, AWS, SendGrid, Twilio, IPinfo, and Persona.

## 6.6 Suspicious-login detection

On each successful or failed login the application calculates a risk score using these signals:

- Geographic distance vs. last successful login (impossible travel).

- Country never previously used by this user.

- Tor exit node, known-bad VPN, or anonymizing proxy.

- Device fingerprint not previously seen on this account.

- Failed-login velocity from the same IP across multiple accounts.

Risk-based actions:

- Score 0–39: silent allow.

- Score 40–69: require email confirmation link before session is granted full privileges.

- Score 70–100: block, lock account for 15 minutes, send security email to user, write a row tagged is_suspicious.

# 7. Constraints, Risks, and Open Questions

## 7.1 Constraints

- Single founding engineer at MVP — feature scope must reflect realistic velocity.

- Initial budget assumes $500/month infrastructure spend until traction justifies more.

- App Store review can add 1–14 days to mobile release; web releases are not gated by this.

## 7.2 Top risks

| **Risk** | **Likelihood** | **Impact** | **Mitigation** |
| --- | --- | --- | --- |
| Stripe Connect onboarding friction reduces host conversion. | High | High | Inline Stripe Express onboarding with progress indicator; allow listings to be created before payouts are configured. |
| Search performance degrades as listing count grows. | Medium | High | PostGIS index on location_geog from day one; consider OpenSearch only if p95 exceeds SLO. |
| Off-platform booking — guests and hosts try to bypass fees. | High | Medium | Mask phone numbers and URLs in pre-booking messages; trust and safety review of repeat behavior. |
| A high-profile dispute or fraud event damages early brand trust. | Medium | High | Manual listing approval at MVP, ID verification before payouts above threshold, prompt refund authority for support. |
| Apple or Google reject the app for marketplace policy reasons. | Medium | High | Pre-submission policy review; clearly disclose host fees in IAP-exempt categories; do not collect digital goods commissions. |
| Trademark conflict surfaces post-launch. | Low–Medium | High | Conduct USPTO TESS search and a legal clearance review before public launch. |

## 7.3 Open questions

- Are we launching in a single market (Florida) or nationally on day one?

- Do we accept hosts from outside the US for v1.0, or US-only until Stripe Connect international flows are proven in our stack?

- Are we treating Vaytoven Rentals LLC as the merchant of record, or are hosts the merchants of record?

- Do we offer host damage protection at launch (this materially changes underwriting and reserves)?

# 8. Acceptance and Sign-off

The following stakeholders acknowledge that this SRS defines the v1.0 scope. Material changes after sign-off must be tracked through an explicit change-request process.

 

| **Role** | **Name** | **Date** | **Signature** |
| --- | --- | --- | --- |
| Product Owner |  |  |  |
| Engineering Lead |  |  |  |
| Legal / Privacy |  |  |  |
| Investor / Advisor |  |  |  |

Page  of