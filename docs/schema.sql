-- Vaytoven Rentals — Canonical schema reference
--
-- This file is documentation, NOT executable. Migrations in database/migrations/
-- are the runtime source of truth. Update this file in the same PR as any
-- migration that adds, modifies, or removes a column.
--
-- DBMS: MySQL 8.4 (Laravel Cloud). Where Postgres-specific patterns are referenced
-- in the master implementation prompt (PostGIS, EXCLUDE constraints, JSONB), the
-- MySQL substitute is documented inline.
--
-- Status: 🟢 = migrated and shipped · 🟡 = in progress · 🔲 = planned (no migration yet)

-- =============================================================================
-- 🟢 LARAVEL DEFAULTS — shipped via framework's built-in migrations
-- =============================================================================

-- users (Laravel 11 default)
-- 🟢 status: shipped
-- ----------------------
-- id BIGINT UNSIGNED PK
-- name VARCHAR(255)
-- email VARCHAR(255) UNIQUE
-- email_verified_at TIMESTAMP NULL
-- password VARCHAR(255)              -- bcrypt hash via 'hashed' cast
-- remember_token VARCHAR(100) NULL
-- created_at, updated_at TIMESTAMP
--
-- 🔲 PHASE 1 ADDITIONS
-- role ENUM('traveler','host','member','admin','super_admin') DEFAULT 'traveler'
-- two_factor_secret TEXT NULL
-- two_factor_recovery_codes TEXT NULL

-- sessions, password_reset_tokens, cache, cache_locks, jobs, job_batches, failed_jobs
-- 🟢 status: shipped (all Laravel 11 defaults)

-- =============================================================================
-- 🟢 EXISTING DOMAIN TABLES — already shipped to development
-- =============================================================================

-- contracts (DocuSign envelopes)
-- 🟢 status: shipped — see 2026_05_03_000001_create_contracts_table.php
CREATE TABLE contracts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    envelope_id VARCHAR(64) UNIQUE,
    template_id VARCHAR(64),
    status ENUM('draft','sent','delivered','signed','declined','voided','expired') NOT NULL,
    subject VARCHAR(255),
    sent_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    voided_at TIMESTAMP NULL,
    voided_reason VARCHAR(255) NULL,
    metadata JSON NULL,
    created_at TIMESTAMP, updated_at TIMESTAMP,
    INDEX (user_id),
    INDEX (status)
);

-- contract_events (audit log of envelope state transitions)
-- 🟢 status: shipped — see 2026_05_03_000002_create_contract_events_table.php
CREATE TABLE contract_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(64) NOT NULL,
    event_data JSON,
    occurred_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP,
    INDEX (contract_id),
    INDEX (event_type),
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE
);

-- members_enquiries (Managed Listing Program funnel)
-- 🟢 status: shipped — see 2026_05_04_000001_create_members_enquiries_table.php
CREATE TABLE members_enquiries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(64) NULL,
    program ENUM('marriott','hilton','disney','rci','interval','other') NOT NULL,
    points INT UNSIGNED NULL,
    notes TEXT NULL,
    status ENUM('new','contacted','qualified','onboarded','disqualified') DEFAULT 'new',
    assigned_specialist_id BIGINT UNSIGNED NULL,
    converted_property_id BIGINT UNSIGNED NULL,        -- 🔲 FK pending Phase 2 (properties table)
    source VARCHAR(64) NULL,                            -- 'web_modal' | 'app_banner' | etc.
    utm_payload JSON NULL,                              -- captured at submission
    contacted_at TIMESTAMP NULL,
    qualified_at TIMESTAMP NULL,
    onboarded_at TIMESTAMP NULL,
    disqualified_at TIMESTAMP NULL,
    disqualified_reason VARCHAR(255) NULL,
    created_at TIMESTAMP, updated_at TIMESTAMP,
    INDEX (status),
    INDEX (assigned_specialist_id)
);

-- =============================================================================
-- 🟢 PHASE 2A (shipped) — properties + amenities + property_amenities + property_photos
-- =============================================================================

-- properties — vacation rental listings
-- 🟢 status: shipped (2026_05_06_000011)
-- NOTE: location stored as DECIMAL latitude/longitude (NOT POINT) for SQLite test
-- portability. Bounding-box prefilter in app code; spatial column to be added in
-- a follow-up MySQL-only migration once search load is measured.
CREATE TABLE properties (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    host_id BIGINT UNSIGNED NOT NULL,
    listing_source ENUM('host','managed') DEFAULT 'host',
    title VARCHAR(255) NOT NULL,
    description MEDIUMTEXT NULL,
    location POINT SRID 4326 NOT NULL,                 -- MySQL spatial; replaces PostGIS geography
    address_line VARCHAR(255),
    city VARCHAR(128),
    region VARCHAR(128),
    country CHAR(2),                                    -- ISO-3166 alpha-2
    postal_code VARCHAR(32),
    capacity TINYINT UNSIGNED,
    bedrooms TINYINT UNSIGNED,
    beds TINYINT UNSIGNED,
    bathrooms DECIMAL(3,1),                             -- 1.0, 1.5, 2.0 (NOT money — display only)
    base_nightly_cents INT UNSIGNED NOT NULL,           -- money: integer cents
    cleaning_fee_cents INT UNSIGNED DEFAULT 0,
    cancellation_policy ENUM('flexible','moderate','strict','non_refundable') DEFAULT 'moderate',
    minimum_nights TINYINT UNSIGNED DEFAULT 1,
    status ENUM('draft','pending_review','active','paused','archived') DEFAULT 'draft',
    payout_account_id BIGINT UNSIGNED NULL,             -- FK to host_payout_accounts
    converted_from_enquiry_id BIGINT UNSIGNED NULL,     -- FK to members_enquiries (for managed listings)
    created_at TIMESTAMP, updated_at TIMESTAMP,
    INDEX (host_id), INDEX (status), INDEX (city, country),
    SPATIAL INDEX (location),
    FOREIGN KEY (host_id) REFERENCES users(id)
);

-- amenities — canonical catalogue
CREATE TABLE amenities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(64) UNIQUE NOT NULL,                   -- 'wifi', 'pool', 'kitchen', etc.
    label VARCHAR(128) NOT NULL,
    category ENUM('safety','accessibility','outdoor','indoor','family','workspace','other'),
    icon VARCHAR(64),
    created_at TIMESTAMP, updated_at TIMESTAMP
);

-- property_amenities — join table
CREATE TABLE property_amenities (
    property_id BIGINT UNSIGNED NOT NULL,
    amenity_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (property_id, amenity_id),
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    FOREIGN KEY (amenity_id) REFERENCES amenities(id) ON DELETE CASCADE
);

-- property_photos
CREATE TABLE property_photos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    property_id BIGINT UNSIGNED NOT NULL,
    url VARCHAR(512) NOT NULL,
    sort_order SMALLINT UNSIGNED DEFAULT 0,
    caption VARCHAR(255),
    created_at TIMESTAMP, updated_at TIMESTAMP,
    INDEX (property_id, sort_order),
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
);

-- bookings
-- NOTE: Postgres EXCLUDE substitute = SELECT ... FOR UPDATE in transaction PLUS
-- the unique constraint below. This catches the trivial collision case at the DB
-- level; concurrent attempts on the same dates will both lock the same row range
-- and serialize via InnoDB's gap locks. App-layer code must wrap booking writes
-- in a transaction with `LOCK IN SHARE MODE` on the candidate property row.
CREATE TABLE bookings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    property_id BIGINT UNSIGNED NOT NULL,
    traveler_id BIGINT UNSIGNED NOT NULL,
    confirmation_code CHAR(10) UNIQUE NOT NULL,        -- 'VYT-XXXXXX' format
    check_in_date DATE NOT NULL,
    check_out_date DATE NOT NULL,
    guests TINYINT UNSIGNED NOT NULL,
    nightly_rate_cents INT UNSIGNED NOT NULL,           -- snapshotted at booking time
    nights SMALLINT UNSIGNED NOT NULL,
    subtotal_cents INT UNSIGNED NOT NULL,
    cleaning_fee_cents INT UNSIGNED DEFAULT 0,
    service_fee_cents INT UNSIGNED DEFAULT 0,
    tax_cents INT UNSIGNED DEFAULT 0,
    total_cents INT UNSIGNED NOT NULL,
    cancellation_policy ENUM('flexible','moderate','strict','non_refundable') NOT NULL,
    status ENUM('pending_payment','confirmed','in_progress','completed','cancelled') DEFAULT 'pending_payment',
    cancelled_at TIMESTAMP NULL,
    cancelled_reason VARCHAR(255) NULL,
    payment_intent_id BIGINT UNSIGNED NULL,             -- FK to payment_intents
    created_at TIMESTAMP, updated_at TIMESTAMP,
    UNIQUE KEY uk_no_overlap (property_id, check_in_date, check_out_date),
    INDEX (traveler_id), INDEX (status),
    FOREIGN KEY (property_id) REFERENCES properties(id),
    FOREIGN KEY (traveler_id) REFERENCES users(id)
);

-- booking_state_transitions — audit log
CREATE TABLE booking_state_transitions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT UNSIGNED NOT NULL,
    from_state VARCHAR(32) NULL,                        -- NULL on initial creation
    to_state VARCHAR(32) NOT NULL,
    actor_user_id BIGINT UNSIGNED NULL,                 -- system if NULL
    reason VARCHAR(255),
    occurred_at TIMESTAMP NOT NULL,
    INDEX (booking_id),
    FOREIGN KEY (booking_id) REFERENCES bookings(id)
);

-- =============================================================================
-- 🔲 PHASE 5 — PAYMENTS
-- =============================================================================

-- host_payout_accounts (Stripe Connect Express)
CREATE TABLE host_payout_accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    host_id BIGINT UNSIGNED NOT NULL,
    processor ENUM('stripe','authorizenet','nmi','nuvei','mes','paymentcloud','ems','nexio','netevia','kurv') DEFAULT 'stripe',
    external_account_id VARCHAR(128) NOT NULL,          -- e.g. acct_1Abc... for Stripe
    status ENUM('pending_kyc','verified','restricted','disabled') DEFAULT 'pending_kyc',
    payouts_enabled BOOLEAN DEFAULT FALSE,
    charges_enabled BOOLEAN DEFAULT FALSE,
    last_synced_at TIMESTAMP NULL,
    metadata JSON,
    created_at TIMESTAMP, updated_at TIMESTAMP,
    UNIQUE KEY (processor, external_account_id),
    FOREIGN KEY (host_id) REFERENCES users(id)
);

-- payment_intents
CREATE TABLE payment_intents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT UNSIGNED NOT NULL,
    processor ENUM('stripe','authorizenet','nmi','nuvei','mes','paymentcloud','ems','nexio','netevia','kurv') NOT NULL,
    external_intent_id VARCHAR(128) NOT NULL,
    amount_cents INT UNSIGNED NOT NULL,
    currency CHAR(3) DEFAULT 'USD',
    status ENUM('requires_action','processing','succeeded','requires_payment_method','canceled','failed') NOT NULL,
    metadata JSON,
    created_at TIMESTAMP, updated_at TIMESTAMP,
    UNIQUE KEY (processor, external_intent_id),
    FOREIGN KEY (booking_id) REFERENCES bookings(id)
);

-- charges, refunds, payouts — similar pattern, deferred for now

-- stripe_events (idempotency)
CREATE TABLE stripe_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id VARCHAR(64) UNIQUE NOT NULL,               -- evt_1Abc... — UNIQUE prevents replay
    event_type VARCHAR(64) NOT NULL,
    payload JSON,
    processed_at TIMESTAMP NOT NULL,
    INDEX (event_type)
);
-- One identical table per processor: authorizenet_events, nmi_events, etc.
-- All follow the same pattern with `event_id UNIQUE` as the idempotency key.

-- =============================================================================
-- 🔲 PHASE 6 — TRACKING + CHARGEBACK EVIDENCE
-- =============================================================================

-- tracking_events — append-only audit trail with hash chain
CREATE TABLE tracking_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_uuid CHAR(36) UNIQUE NOT NULL,                -- public reference; NEVER reuse
    event_type VARCHAR(64) NOT NULL,                    -- 'page_view', 'booking_created', etc.
    actor_user_id BIGINT UNSIGNED NULL,                 -- NULL = anonymous
    visitor_id CHAR(36) NULL,                           -- vyt_vid cookie
    surface ENUM('web','app_ios','app_android','admin') NOT NULL,
    ip_address VARBINARY(16) NULL,                      -- INET6
    user_agent VARCHAR(512),
    metadata JSON,
    parent_hash CHAR(64),                               -- prior row's current_hash
    current_hash CHAR(64) NOT NULL,                     -- sha256(parent || event_uuid || type || actor_id || metadata)
    occurred_at TIMESTAMP(6) NOT NULL,
    INDEX (event_type), INDEX (actor_user_id), INDEX (visitor_id), INDEX (occurred_at)
);
-- TRIGGERS (added by Phase 6 migration):
-- CREATE TRIGGER tracking_events_no_update BEFORE UPDATE ON tracking_events
--   FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'tracking_events is append-only';
-- CREATE TRIGGER tracking_events_no_delete BEFORE DELETE ON tracking_events
--   FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'tracking_events is append-only';

-- ppc_visitors — first-touch attribution for paid traffic
CREATE TABLE ppc_visitors (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    visitor_id CHAR(36) UNIQUE NOT NULL,
    first_seen_at TIMESTAMP NOT NULL,
    landing_url VARCHAR(2048),
    utm_source VARCHAR(64), utm_medium VARCHAR(64), utm_campaign VARCHAR(128),
    utm_term VARCHAR(128), utm_content VARCHAR(128),
    gclid VARCHAR(128), fbclid VARCHAR(128),            -- click IDs
    referrer VARCHAR(2048),
    INDEX (utm_source), INDEX (utm_campaign)
);

-- chargeback_disputes
CREATE TABLE chargeback_disputes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NULL,
    processor ENUM('stripe','authorizenet','nmi','nuvei','mes','paymentcloud','ems','nexio','netevia','kurv') NOT NULL,
    external_dispute_id VARCHAR(128) NOT NULL,
    amount_cents INT UNSIGNED NOT NULL,
    reason VARCHAR(255),
    status ENUM('warning_needs_response','needs_response','under_review','won','lost','warning_closed') NOT NULL,
    evidence_due_by TIMESTAMP NULL,
    evidence_submitted_at TIMESTAMP NULL,
    bundle_path VARCHAR(512) NULL,                      -- s3:// or local path
    created_at TIMESTAMP, updated_at TIMESTAMP,
    UNIQUE KEY (processor, external_dispute_id),
    INDEX (booking_id), INDEX (user_id),
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- =============================================================================
-- 🔲 PHASE 7 — AI SUPPORT CHAT
-- =============================================================================

-- support_chat_sessions, support_messages, support_tickets, support_ticket_messages
-- Schema TBD in Phase 7; pattern is one session → many messages, escalation creates a ticket
-- with the chat session linked.

-- =============================================================================
-- 🔲 PHASE 8 — LOGIN TRACKING + TERMS
-- =============================================================================

-- login_sessions
CREATE TABLE login_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    auth_event ENUM('login','logout','failed','lockout') NOT NULL,
    surface ENUM('web','app_ios','app_android','admin') NOT NULL,
    session_id VARCHAR(64) NULL,
    ip_address VARBINARY(16),
    country CHAR(2), region VARCHAR(64), city VARCHAR(128), latitude DECIMAL(9,6), longitude DECIMAL(9,6),
    asn INT UNSIGNED NULL,                              -- autonomous system number
    is_vpn BOOLEAN, is_tor BOOLEAN, is_datacenter BOOLEAN,
    device_type ENUM('desktop','mobile','tablet','unknown'),
    os VARCHAR(64), browser VARCHAR(64),
    user_agent VARCHAR(512),
    is_suspicious BOOLEAN DEFAULT FALSE,
    suspicious_reasons JSON NULL,                       -- ['new_country','new_device','geo_impossible']
    occurred_at TIMESTAMP(6) NOT NULL,
    INDEX (user_id, occurred_at), INDEX (auth_event),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- terms_versions
CREATE TABLE terms_versions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kind ENUM('tos','chargeback','privacy','member_agreement') NOT NULL,
    version_label VARCHAR(64) NOT NULL,                 -- '2026-05-01' or 'v1.2'
    content_hash CHAR(64) UNIQUE NOT NULL,              -- SHA-256 of canonical text
    content_url VARCHAR(512) NOT NULL,                  -- where the canonical text lives
    effective_at TIMESTAMP NOT NULL,
    superseded_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    INDEX (kind, effective_at)
);

-- terms_acceptances
CREATE TABLE terms_acceptances (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    terms_version_id BIGINT UNSIGNED NOT NULL,
    accepted_at TIMESTAMP NOT NULL,
    ip_address VARBINARY(16),
    user_agent VARCHAR(512),
    UNIQUE KEY (user_id, terms_version_id),             -- one accept per user per version
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (terms_version_id) REFERENCES terms_versions(id)
);

-- =============================================================================
-- 🔲 PHASE 7 — ADMIN AUDIT
-- =============================================================================

-- admin_audit_logs
CREATE TABLE admin_audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_user_id BIGINT UNSIGNED NOT NULL,             -- the admin who did it
    action VARCHAR(128) NOT NULL,                       -- 'user.suspend', 'enquiry.transition', etc.
    subject_type VARCHAR(128),                          -- e.g. 'App\Models\User'
    subject_id BIGINT UNSIGNED,
    payload JSON,
    ip_address VARBINARY(16),
    occurred_at TIMESTAMP(6) NOT NULL,
    INDEX (actor_user_id), INDEX (subject_type, subject_id), INDEX (action),
    FOREIGN KEY (actor_user_id) REFERENCES users(id)
);

-- =============================================================================
-- DEFERRED / OUT OF SCOPE FOR MVP
-- =============================================================================
-- reviews, review_responses (Phase 5)
-- wishlists, wishlist_properties (Phase 5)
-- message_threads, messages (Phase 5)
-- member_specialist_assignments (Phase 4)
