-- =====================================================================
-- VAYTOVEN RENTALS — DATABASE SCHEMA (PostgreSQL 15+)
-- =====================================================================
-- Version:    1.0.0
-- Engine:     PostgreSQL 15+ (also runs on MySQL 8 with minor edits;
--             see notes at bottom of file)
-- Conventions:
--   * snake_case for tables and columns
--   * Singular FK column names: `user_id`, `property_id`
--   * Soft deletes via `deleted_at` where appropriate
--   * `created_at` / `updated_at` on every entity table
--   * Money stored as BIGINT cents (avoids floating-point errors)
--   * UUIDs for externally-exposed IDs, BIGSERIAL for internal joins
-- =====================================================================

CREATE EXTENSION IF NOT EXISTS "pgcrypto";   -- for gen_random_uuid()
CREATE EXTENSION IF NOT EXISTS "citext";     -- case-insensitive emails
CREATE EXTENSION IF NOT EXISTS "postgis";    -- geo queries (optional but recommended)

-- =====================================================================
-- 1. ROLES & USERS
-- =====================================================================

CREATE TABLE roles (
    id              SMALLSERIAL PRIMARY KEY,
    slug            VARCHAR(32)  NOT NULL UNIQUE,        -- 'guest','host','admin','super_admin'
    name            VARCHAR(64)  NOT NULL,
    description     TEXT,
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

INSERT INTO roles (slug, name) VALUES
    ('guest',        'Guest'),
    ('host',         'Host'),
    ('admin',        'Admin'),
    ('super_admin',  'Super Admin');

CREATE TABLE users (
    id                          BIGSERIAL PRIMARY KEY,
    uuid                        UUID         NOT NULL UNIQUE DEFAULT gen_random_uuid(),
    email                       CITEXT       NOT NULL UNIQUE,
    email_verified_at           TIMESTAMPTZ,
    phone                       VARCHAR(32),
    phone_verified_at           TIMESTAMPTZ,
    password_hash               VARCHAR(255) NOT NULL,             -- bcrypt/argon2
    first_name                  VARCHAR(80),
    last_name                   VARCHAR(80),
    display_name                VARCHAR(120),
    avatar_url                  TEXT,
    bio                         TEXT,
    date_of_birth               DATE,
    primary_role_id             SMALLINT     NOT NULL REFERENCES roles(id),
    is_host                     BOOLEAN      NOT NULL DEFAULT FALSE,
    is_superhost                BOOLEAN      NOT NULL DEFAULT FALSE,
    host_approved_at            TIMESTAMPTZ,
    government_id_verified_at   TIMESTAMPTZ,
    locale                      VARCHAR(10)  NOT NULL DEFAULT 'en_US',
    timezone                    VARCHAR(64)  NOT NULL DEFAULT 'UTC',
    currency                    CHAR(3)      NOT NULL DEFAULT 'USD',
    -- Privacy & consent (GDPR/CCPA)
    marketing_consent           BOOLEAN      NOT NULL DEFAULT FALSE,
    privacy_policy_version      VARCHAR(20),
    privacy_accepted_at         TIMESTAMPTZ,
    tos_version                 VARCHAR(20),
    tos_accepted_at             TIMESTAMPTZ,
    -- Security
    two_factor_secret           VARCHAR(255),
    two_factor_enabled_at       TIMESTAMPTZ,
    last_password_change_at     TIMESTAMPTZ,
    failed_login_count          INTEGER      NOT NULL DEFAULT 0,
    locked_until                TIMESTAMPTZ,
    status                      VARCHAR(20)  NOT NULL DEFAULT 'active',  -- active|suspended|banned|deleted
    -- Stripe
    stripe_customer_id          VARCHAR(64),
    stripe_connect_account_id   VARCHAR(64),                              -- only for hosts
    --
    created_at                  TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at                  TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    deleted_at                  TIMESTAMPTZ
);

CREATE INDEX idx_users_email          ON users(email);
CREATE INDEX idx_users_status         ON users(status) WHERE deleted_at IS NULL;
CREATE INDEX idx_users_is_host        ON users(is_host) WHERE is_host = TRUE;

-- Multi-role support (a user can be both host and guest)
CREATE TABLE user_roles (
    user_id    BIGINT     NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role_id    SMALLINT   NOT NULL REFERENCES roles(id),
    granted_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (user_id, role_id)
);


-- =====================================================================
-- 2. PROPERTIES
-- =====================================================================

CREATE TABLE property_types (
    id          SMALLSERIAL PRIMARY KEY,
    slug        VARCHAR(40) NOT NULL UNIQUE,   -- 'apartment','house','villa','cabin','condo','tiny_home'
    name        VARCHAR(80) NOT NULL,
    icon        VARCHAR(40),
    sort_order  INTEGER     NOT NULL DEFAULT 0
);

CREATE TABLE properties (
    id                      BIGSERIAL PRIMARY KEY,
    uuid                    UUID         NOT NULL UNIQUE DEFAULT gen_random_uuid(),
    host_id                 BIGINT       NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    property_type_id        SMALLINT     NOT NULL REFERENCES property_types(id),
    title                   VARCHAR(120) NOT NULL,
    slug                    VARCHAR(160) NOT NULL UNIQUE,
    description             TEXT         NOT NULL,
    summary                 VARCHAR(500),
    -- Location
    address_line1           VARCHAR(255),
    address_line2           VARCHAR(255),
    city                    VARCHAR(120) NOT NULL,
    state                   VARCHAR(120),
    country_code            CHAR(2)      NOT NULL,
    postal_code             VARCHAR(20),
    latitude                DECIMAL(10,7) NOT NULL,
    longitude               DECIMAL(10,7) NOT NULL,
    location_geog           GEOGRAPHY(Point, 4326),               -- generated below
    timezone                VARCHAR(64),
    -- Capacity
    max_guests              SMALLINT     NOT NULL DEFAULT 2,
    bedrooms                SMALLINT     NOT NULL DEFAULT 1,
    beds                    SMALLINT     NOT NULL DEFAULT 1,
    bathrooms               DECIMAL(3,1) NOT NULL DEFAULT 1.0,    -- supports half-baths
    -- Pricing (cents)
    base_price_cents        BIGINT       NOT NULL,                -- per night
    cleaning_fee_cents      BIGINT       NOT NULL DEFAULT 0,
    extra_guest_fee_cents   BIGINT       NOT NULL DEFAULT 0,
    weekly_discount_pct     DECIMAL(5,2) NOT NULL DEFAULT 0,
    monthly_discount_pct    DECIMAL(5,2) NOT NULL DEFAULT 0,
    currency                CHAR(3)      NOT NULL DEFAULT 'USD',
    -- Stay rules
    min_nights              SMALLINT     NOT NULL DEFAULT 1,
    max_nights              SMALLINT     NOT NULL DEFAULT 30,
    check_in_time           TIME         NOT NULL DEFAULT '15:00',
    check_out_time          TIME         NOT NULL DEFAULT '11:00',
    advance_notice_hours    SMALLINT     NOT NULL DEFAULT 24,
    instant_book            BOOLEAN      NOT NULL DEFAULT FALSE,
    -- Status / moderation
    status                  VARCHAR(20)  NOT NULL DEFAULT 'draft',
                                                                    -- draft|pending_review|active|paused|rejected|archived
    rejection_reason        TEXT,
    approved_by             BIGINT       REFERENCES users(id),
    approved_at             TIMESTAMPTZ,
    -- Stats (denormalized, refreshed by job)
    rating_avg              DECIMAL(3,2),
    rating_count            INTEGER      NOT NULL DEFAULT 0,
    bookings_count          INTEGER      NOT NULL DEFAULT 0,
    --
    created_at              TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at              TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    deleted_at              TIMESTAMPTZ
);

-- Auto-populate the geography point from lat/lng
CREATE OR REPLACE FUNCTION sync_property_geog() RETURNS TRIGGER AS $$
BEGIN
    NEW.location_geog := ST_SetSRID(ST_MakePoint(NEW.longitude, NEW.latitude), 4326)::geography;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_properties_geog
BEFORE INSERT OR UPDATE OF latitude, longitude ON properties
FOR EACH ROW EXECUTE FUNCTION sync_property_geog();

CREATE INDEX idx_properties_host_id      ON properties(host_id);
CREATE INDEX idx_properties_status       ON properties(status) WHERE deleted_at IS NULL;
CREATE INDEX idx_properties_city         ON properties(city);
CREATE INDEX idx_properties_country      ON properties(country_code);
CREATE INDEX idx_properties_geog_gist    ON properties USING GIST(location_geog);
CREATE INDEX idx_properties_active_geog  ON properties USING GIST(location_geog)
                                          WHERE status = 'active' AND deleted_at IS NULL;

-- Property images
CREATE TABLE property_images (
    id              BIGSERIAL PRIMARY KEY,
    property_id     BIGINT       NOT NULL REFERENCES properties(id) ON DELETE CASCADE,
    url             TEXT         NOT NULL,
    storage_path    TEXT,                                       -- s3://bucket/key
    caption         VARCHAR(255),
    sort_order      INTEGER      NOT NULL DEFAULT 0,
    is_cover        BOOLEAN      NOT NULL DEFAULT FALSE,
    width           INTEGER,
    height          INTEGER,
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_property_images_property ON property_images(property_id, sort_order);
CREATE UNIQUE INDEX uq_property_cover ON property_images(property_id) WHERE is_cover = TRUE;

-- Amenities (master list)
CREATE TABLE amenities (
    id          SMALLSERIAL PRIMARY KEY,
    slug        VARCHAR(60) NOT NULL UNIQUE,    -- 'wifi','pool','kitchen','ac','parking'
    name        VARCHAR(80) NOT NULL,
    icon        VARCHAR(40),
    category    VARCHAR(40),                    -- 'essentials','features','safety'
    sort_order  INTEGER     NOT NULL DEFAULT 0
);

CREATE TABLE property_amenities (
    property_id BIGINT   NOT NULL REFERENCES properties(id) ON DELETE CASCADE,
    amenity_id  SMALLINT NOT NULL REFERENCES amenities(id),
    PRIMARY KEY (property_id, amenity_id)
);

-- House rules (free-form per property)
CREATE TABLE property_rules (
    id          BIGSERIAL PRIMARY KEY,
    property_id BIGINT       NOT NULL REFERENCES properties(id) ON DELETE CASCADE,
    rule        VARCHAR(255) NOT NULL,
    allowed     BOOLEAN      NOT NULL DEFAULT TRUE,
    sort_order  INTEGER      NOT NULL DEFAULT 0
);

-- Calendar / availability overrides
CREATE TABLE property_availability (
    id              BIGSERIAL PRIMARY KEY,
    property_id     BIGINT       NOT NULL REFERENCES properties(id) ON DELETE CASCADE,
    date            DATE         NOT NULL,
    is_available    BOOLEAN      NOT NULL DEFAULT TRUE,
    price_cents     BIGINT,                                     -- override
    min_nights      SMALLINT,                                   -- override
    note            VARCHAR(255),
    UNIQUE (property_id, date)
);
CREATE INDEX idx_availability_property_date ON property_availability(property_id, date);


-- =====================================================================
-- 3. BOOKINGS
-- =====================================================================

CREATE TABLE bookings (
    id                      BIGSERIAL PRIMARY KEY,
    uuid                    UUID         NOT NULL UNIQUE DEFAULT gen_random_uuid(),
    confirmation_code       VARCHAR(12)  NOT NULL UNIQUE,        -- human-friendly, e.g. 'VTV-A4F2K9'
    property_id             BIGINT       NOT NULL REFERENCES properties(id),
    host_id                 BIGINT       NOT NULL REFERENCES users(id),
    guest_id                BIGINT       NOT NULL REFERENCES users(id),
    -- Dates
    check_in                DATE         NOT NULL,
    check_out               DATE         NOT NULL,
    nights                  SMALLINT     NOT NULL,
    guests_count            SMALLINT     NOT NULL DEFAULT 1,
    -- Money (cents, snapshot at booking time)
    nightly_rate_cents      BIGINT       NOT NULL,
    subtotal_cents          BIGINT       NOT NULL,
    cleaning_fee_cents      BIGINT       NOT NULL DEFAULT 0,
    service_fee_cents       BIGINT       NOT NULL DEFAULT 0,    -- platform fee charged to guest
    taxes_cents             BIGINT       NOT NULL DEFAULT 0,
    discount_cents          BIGINT       NOT NULL DEFAULT 0,
    total_cents             BIGINT       NOT NULL,
    host_payout_cents       BIGINT       NOT NULL,              -- after platform commission
    platform_fee_cents      BIGINT       NOT NULL,              -- combined guest + host fees
    currency                CHAR(3)      NOT NULL,
    -- Status
    status                  VARCHAR(20)  NOT NULL DEFAULT 'pending',
        -- pending|requested|confirmed|cancelled_by_guest|cancelled_by_host|
        -- declined|completed|no_show|refunded
    booked_at               TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    confirmed_at            TIMESTAMPTZ,
    cancelled_at            TIMESTAMPTZ,
    cancellation_reason     TEXT,
    cancellation_policy     VARCHAR(20)  NOT NULL DEFAULT 'moderate',
                                                                  -- flexible|moderate|strict
    guest_message           TEXT,
    -- Timestamps
    created_at              TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at              TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    CHECK (check_out > check_in),
    CHECK (guests_count >= 1)
);

CREATE INDEX idx_bookings_property        ON bookings(property_id, check_in, check_out);
CREATE INDEX idx_bookings_guest           ON bookings(guest_id);
CREATE INDEX idx_bookings_host            ON bookings(host_id);
CREATE INDEX idx_bookings_status          ON bookings(status);
CREATE INDEX idx_bookings_dates           ON bookings(check_in, check_out);

-- Prevent overlapping confirmed bookings (PostgreSQL EXCLUSION constraint)
CREATE EXTENSION IF NOT EXISTS btree_gist;
ALTER TABLE bookings
    ADD CONSTRAINT bookings_no_overlap
    EXCLUDE USING gist (
        property_id WITH =,
        daterange(check_in, check_out, '[)') WITH &&
    )
    WHERE (status IN ('confirmed', 'completed'));


-- =====================================================================
-- 4. PAYMENTS & PAYOUTS
-- =====================================================================

CREATE TABLE payments (
    id                      BIGSERIAL PRIMARY KEY,
    uuid                    UUID         NOT NULL UNIQUE DEFAULT gen_random_uuid(),
    booking_id              BIGINT       NOT NULL REFERENCES bookings(id),
    user_id                 BIGINT       NOT NULL REFERENCES users(id),    -- guest who paid
    amount_cents            BIGINT       NOT NULL,
    currency                CHAR(3)      NOT NULL,
    payment_method          VARCHAR(40),                                   -- 'card','apple_pay','google_pay'
    -- Stripe
    stripe_payment_intent   VARCHAR(64),
    stripe_charge_id        VARCHAR(64),
    stripe_refund_id        VARCHAR(64),
    last4                   CHAR(4),
    card_brand              VARCHAR(20),
    -- Status
    status                  VARCHAR(20)  NOT NULL DEFAULT 'pending',
        -- pending|authorized|captured|failed|refunded|partial_refund|disputed
    failure_code            VARCHAR(40),
    failure_message         TEXT,
    paid_at                 TIMESTAMPTZ,
    refunded_at             TIMESTAMPTZ,
    refund_amount_cents     BIGINT       DEFAULT 0,
    --
    created_at              TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at              TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_payments_booking         ON payments(booking_id);
CREATE INDEX idx_payments_user            ON payments(user_id);
CREATE INDEX idx_payments_status          ON payments(status);
CREATE INDEX idx_payments_stripe_intent   ON payments(stripe_payment_intent);

-- Saved payment methods (PCI-safe: only token + last4, never PAN)
CREATE TABLE payment_methods (
    id                      BIGSERIAL PRIMARY KEY,
    user_id                 BIGINT       NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    stripe_payment_method   VARCHAR(64)  NOT NULL,
    type                    VARCHAR(20)  NOT NULL,              -- 'card','apple_pay'
    brand                   VARCHAR(20),
    last4                   CHAR(4),
    exp_month               SMALLINT,
    exp_year                SMALLINT,
    is_default              BOOLEAN      NOT NULL DEFAULT FALSE,
    created_at              TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);
CREATE UNIQUE INDEX uq_default_pm ON payment_methods(user_id) WHERE is_default = TRUE;

-- Host payouts (Stripe Connect)
CREATE TABLE payouts (
    id                          BIGSERIAL PRIMARY KEY,
    uuid                        UUID         NOT NULL UNIQUE DEFAULT gen_random_uuid(),
    host_id                     BIGINT       NOT NULL REFERENCES users(id),
    booking_id                  BIGINT       REFERENCES bookings(id),     -- nullable for batched payouts
    amount_cents                BIGINT       NOT NULL,
    currency                    CHAR(3)      NOT NULL,
    stripe_transfer_id          VARCHAR(64),
    stripe_payout_id            VARCHAR(64),
    status                      VARCHAR(20)  NOT NULL DEFAULT 'pending',
        -- pending|in_transit|paid|failed|reversed
    scheduled_for               TIMESTAMPTZ,
    paid_at                     TIMESTAMPTZ,
    failure_message             TEXT,
    created_at                  TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at                  TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_payouts_host    ON payouts(host_id);
CREATE INDEX idx_payouts_status  ON payouts(status);


-- =====================================================================
-- 5. REVIEWS
-- =====================================================================

CREATE TABLE reviews (
    id                  BIGSERIAL PRIMARY KEY,
    uuid                UUID         NOT NULL UNIQUE DEFAULT gen_random_uuid(),
    booking_id          BIGINT       NOT NULL REFERENCES bookings(id),
    property_id         BIGINT       NOT NULL REFERENCES properties(id),
    reviewer_id         BIGINT       NOT NULL REFERENCES users(id),
    reviewee_id         BIGINT       NOT NULL REFERENCES users(id),
    review_type         VARCHAR(20)  NOT NULL,                    -- 'guest_to_host'|'host_to_guest'
    rating_overall      SMALLINT     NOT NULL CHECK (rating_overall BETWEEN 1 AND 5),
    rating_cleanliness  SMALLINT     CHECK (rating_cleanliness BETWEEN 1 AND 5),
    rating_accuracy     SMALLINT     CHECK (rating_accuracy BETWEEN 1 AND 5),
    rating_communication SMALLINT    CHECK (rating_communication BETWEEN 1 AND 5),
    rating_location     SMALLINT     CHECK (rating_location BETWEEN 1 AND 5),
    rating_check_in     SMALLINT     CHECK (rating_check_in BETWEEN 1 AND 5),
    rating_value        SMALLINT     CHECK (rating_value BETWEEN 1 AND 5),
    public_comment      TEXT,
    private_comment     TEXT,
    is_published        BOOLEAN      NOT NULL DEFAULT FALSE,
    published_at        TIMESTAMPTZ,
    flagged             BOOLEAN      NOT NULL DEFAULT FALSE,
    created_at          TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    UNIQUE (booking_id, reviewer_id)        -- one review per booking per direction
);
CREATE INDEX idx_reviews_property ON reviews(property_id) WHERE is_published = TRUE;
CREATE INDEX idx_reviews_reviewee ON reviews(reviewee_id) WHERE is_published = TRUE;


-- =====================================================================
-- 6. MESSAGING
-- =====================================================================

CREATE TABLE message_threads (
    id              BIGSERIAL PRIMARY KEY,
    uuid            UUID         NOT NULL UNIQUE DEFAULT gen_random_uuid(),
    booking_id      BIGINT       REFERENCES bookings(id),       -- nullable: pre-booking inquiries
    property_id     BIGINT       REFERENCES properties(id),
    last_message_at TIMESTAMPTZ,
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE TABLE message_thread_participants (
    thread_id       BIGINT       NOT NULL REFERENCES message_threads(id) ON DELETE CASCADE,
    user_id         BIGINT       NOT NULL REFERENCES users(id),
    last_read_at    TIMESTAMPTZ,
    muted           BOOLEAN      NOT NULL DEFAULT FALSE,
    PRIMARY KEY (thread_id, user_id)
);

CREATE TABLE messages (
    id              BIGSERIAL PRIMARY KEY,
    thread_id       BIGINT       NOT NULL REFERENCES message_threads(id) ON DELETE CASCADE,
    sender_id       BIGINT       NOT NULL REFERENCES users(id),
    body            TEXT         NOT NULL,
    attachments     JSONB,                                       -- [{url,type,size}]
    is_system       BOOLEAN      NOT NULL DEFAULT FALSE,         -- automated messages
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    edited_at       TIMESTAMPTZ,
    deleted_at      TIMESTAMPTZ
);
CREATE INDEX idx_messages_thread ON messages(thread_id, created_at DESC);


-- =====================================================================
-- 7. WISHLISTS
-- =====================================================================

CREATE TABLE wishlists (
    id          BIGSERIAL PRIMARY KEY,
    uuid        UUID         NOT NULL UNIQUE DEFAULT gen_random_uuid(),
    user_id     BIGINT       NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name        VARCHAR(120) NOT NULL DEFAULT 'My Wishlist',
    is_private  BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE TABLE wishlist_items (
    wishlist_id BIGINT      NOT NULL REFERENCES wishlists(id) ON DELETE CASCADE,
    property_id BIGINT      NOT NULL REFERENCES properties(id) ON DELETE CASCADE,
    added_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (wishlist_id, property_id)
);


-- =====================================================================
-- 8. SECURITY: LOGIN ACTIVITY & IP/LOCATION TRACKING
-- =====================================================================
-- Designed for GDPR/CCPA compliance:
--   * Approximate location only (city/region from IP geolocation)
--   * Exact GPS NEVER stored unless user explicitly opts in via app permission
--   * Auto-purge after 365 days (handled by scheduled job)
--   * User can request export and deletion of their security log
-- =====================================================================

CREATE TABLE login_activity (
    id                  BIGSERIAL PRIMARY KEY,
    user_id             BIGINT       NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    event_type          VARCHAR(30)  NOT NULL,
        -- 'login_success','login_failed','logout','password_reset',
        -- '2fa_challenge','2fa_success','2fa_failed','session_expired'
    -- Network
    ip_address          INET         NOT NULL,
    ip_address_hash     VARCHAR(64),                             -- optional: SHA-256 for analytics after IP purge
    user_agent          TEXT,
    -- Approx location from IP (no GPS)
    country_code        CHAR(2),
    country_name        VARCHAR(80),
    region              VARCHAR(120),
    city                VARCHAR(120),
    postal_code         VARCHAR(20),
    timezone            VARCHAR(64),
    -- Device fingerprint
    device_type         VARCHAR(30),                             -- 'mobile','tablet','desktop'
    os_name             VARCHAR(40),
    os_version          VARCHAR(40),
    browser_name        VARCHAR(40),
    browser_version     VARCHAR(40),
    device_id           VARCHAR(120),                            -- mobile install ID
    -- Risk
    is_suspicious       BOOLEAN      NOT NULL DEFAULT FALSE,
    risk_score          SMALLINT,                                -- 0-100
    risk_reason         VARCHAR(255),                            -- 'new_country','impossible_travel','tor_exit_node'
    --
    session_id          VARCHAR(120),
    failure_reason      VARCHAR(80),                             -- 'wrong_password','user_locked','user_not_found'
    occurred_at         TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_login_user_time      ON login_activity(user_id, occurred_at DESC);
CREATE INDEX idx_login_suspicious     ON login_activity(is_suspicious) WHERE is_suspicious = TRUE;
CREATE INDEX idx_login_ip             ON login_activity(ip_address);
CREATE INDEX idx_login_event_type     ON login_activity(event_type);

-- Purge job target (run daily): DELETE FROM login_activity WHERE occurred_at < NOW() - INTERVAL '365 days';

-- IP geolocation cache (separated so we can keep aggregate stats after PII purge)
CREATE TABLE ip_location_logs (
    id                  BIGSERIAL PRIMARY KEY,
    ip_address          INET         NOT NULL,
    ip_address_hash     VARCHAR(64)  NOT NULL,
    country_code        CHAR(2),
    region              VARCHAR(120),
    city                VARCHAR(120),
    latitude            DECIMAL(10,7),                           -- IP-derived, NOT user GPS
    longitude           DECIMAL(10,7),                           -- IP-derived, NOT user GPS
    isp                 VARCHAR(120),
    is_vpn              BOOLEAN,
    is_tor              BOOLEAN,
    is_proxy            BOOLEAN,
    looked_up_at        TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    expires_at          TIMESTAMPTZ                              -- TTL on cache
);
CREATE UNIQUE INDEX uq_iploc_ip_day ON ip_location_logs(ip_address_hash, DATE(looked_up_at));

-- User sessions (for active session list + remote logout)
CREATE TABLE user_sessions (
    id              BIGSERIAL PRIMARY KEY,
    uuid            UUID         NOT NULL UNIQUE DEFAULT gen_random_uuid(),
    user_id         BIGINT       NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash      VARCHAR(255) NOT NULL UNIQUE,                -- never store raw token
    device_id       VARCHAR(120),
    device_label    VARCHAR(120),                                -- 'iPhone 15 Pro', 'Chrome on macOS'
    ip_address      INET,
    last_active_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    expires_at      TIMESTAMPTZ  NOT NULL,
    revoked_at      TIMESTAMPTZ,
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_sessions_user ON user_sessions(user_id) WHERE revoked_at IS NULL;


-- =====================================================================
-- 9. DISPUTES & TRUST/SAFETY
-- =====================================================================

CREATE TABLE disputes (
    id                  BIGSERIAL PRIMARY KEY,
    uuid                UUID         NOT NULL UNIQUE DEFAULT gen_random_uuid(),
    booking_id          BIGINT       NOT NULL REFERENCES bookings(id),
    raised_by_id        BIGINT       NOT NULL REFERENCES users(id),
    against_user_id     BIGINT       NOT NULL REFERENCES users(id),
    category            VARCHAR(40)  NOT NULL,
        -- 'damage','no_show','cancellation','misrepresentation','safety','refund_request','other'
    subject             VARCHAR(255) NOT NULL,
    description         TEXT         NOT NULL,
    requested_refund_cents BIGINT,
    evidence            JSONB,                                     -- array of {url, type, description}
    status              VARCHAR(20)  NOT NULL DEFAULT 'open',
        -- open|under_review|awaiting_response|resolved_in_favor|resolved_against|closed|escalated
    assigned_admin_id   BIGINT       REFERENCES users(id),
    resolution          TEXT,
    refund_amount_cents BIGINT,
    resolved_at         TIMESTAMPTZ,
    created_at          TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_disputes_status   ON disputes(status);
CREATE INDEX idx_disputes_booking  ON disputes(booking_id);
CREATE INDEX idx_disputes_admin    ON disputes(assigned_admin_id);

-- Generic content reports (a user reports a listing/profile/review)
CREATE TABLE content_reports (
    id              BIGSERIAL PRIMARY KEY,
    reporter_id     BIGINT       NOT NULL REFERENCES users(id),
    target_type     VARCHAR(30)  NOT NULL,  -- 'property','user','review','message'
    target_id       BIGINT       NOT NULL,
    reason          VARCHAR(60)  NOT NULL,
    description     TEXT,
    status          VARCHAR(20)  NOT NULL DEFAULT 'pending',
    handled_by      BIGINT       REFERENCES users(id),
    handled_at      TIMESTAMPTZ,
    action_taken    VARCHAR(60),                                  -- 'no_action','warning','removed','suspended','banned'
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_reports_target ON content_reports(target_type, target_id);
CREATE INDEX idx_reports_status ON content_reports(status);


-- =====================================================================
-- 10. NOTIFICATIONS
-- =====================================================================

CREATE TABLE notifications (
    id              BIGSERIAL PRIMARY KEY,
    uuid            UUID         NOT NULL UNIQUE DEFAULT gen_random_uuid(),
    user_id         BIGINT       NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type            VARCHAR(60)  NOT NULL,
        -- 'booking_request','booking_confirmed','booking_cancelled','message_received',
        -- 'review_received','payout_sent','suspicious_login','price_drop'
    title           VARCHAR(255) NOT NULL,
    body            TEXT,
    data            JSONB,                                       -- contextual payload
    action_url      TEXT,
    icon            VARCHAR(40),
    is_read         BOOLEAN      NOT NULL DEFAULT FALSE,
    read_at         TIMESTAMPTZ,
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_notifications_user_unread ON notifications(user_id, created_at DESC) WHERE is_read = FALSE;
CREATE INDEX idx_notifications_user        ON notifications(user_id, created_at DESC);

-- Per-user notification preferences (per channel × per type)
CREATE TABLE notification_preferences (
    user_id     BIGINT      NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type        VARCHAR(60) NOT NULL,
    email       BOOLEAN     NOT NULL DEFAULT TRUE,
    sms         BOOLEAN     NOT NULL DEFAULT FALSE,
    push        BOOLEAN     NOT NULL DEFAULT TRUE,
    in_app      BOOLEAN     NOT NULL DEFAULT TRUE,
    PRIMARY KEY (user_id, type)
);

-- Push token registry (mobile)
CREATE TABLE push_tokens (
    id              BIGSERIAL PRIMARY KEY,
    user_id         BIGINT       NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    platform        VARCHAR(10)  NOT NULL,                       -- 'ios'|'android'|'web'
    token           VARCHAR(500) NOT NULL UNIQUE,
    device_label    VARCHAR(120),
    is_active       BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    last_used_at    TIMESTAMPTZ
);


-- =====================================================================
-- 11. ADMIN AUDIT LOG
-- =====================================================================

CREATE TABLE admin_audit_logs (
    id              BIGSERIAL PRIMARY KEY,
    admin_user_id   BIGINT       NOT NULL REFERENCES users(id),
    action          VARCHAR(80)  NOT NULL,
        -- 'user.suspended','user.unbanned','property.approved','property.rejected',
        -- 'booking.refunded','dispute.resolved','payout.released','impersonation.start',
        -- 'members_enquiry.assigned','members_enquiry.contacted','members_enquiry.qualified',
        -- 'members_enquiry.onboarded','members_enquiry.rejected','members_enquiry.exported'
    target_type     VARCHAR(40),                                  -- 'user','property','booking','members_enquiry'
    target_id       BIGINT,                                       -- for legacy bigint-keyed tables
    target_uuid     UUID,                                         -- for uuid-keyed tables (properties, bookings, members_enquiries)
    before_state    JSONB,
    after_state     JSONB,
    reason          TEXT,
    ip_address      INET,
    user_agent      TEXT,
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    CHECK (target_id IS NOT NULL OR target_uuid IS NOT NULL OR target_type IS NULL)
);
CREATE INDEX idx_admin_audit_admin   ON admin_audit_logs(admin_user_id, created_at DESC);
CREATE INDEX idx_admin_audit_target  ON admin_audit_logs(target_type, target_id);
CREATE INDEX idx_admin_audit_uuid    ON admin_audit_logs(target_type, target_uuid);
CREATE INDEX idx_admin_audit_action  ON admin_audit_logs(action);


-- =====================================================================
-- 12. SUPPORTING TABLES
-- =====================================================================

-- Promo codes
CREATE TABLE promotions (
    id              BIGSERIAL PRIMARY KEY,
    code            VARCHAR(40)  NOT NULL UNIQUE,
    description     VARCHAR(255),
    discount_type   VARCHAR(20)  NOT NULL,                       -- 'percent'|'fixed'
    discount_value  DECIMAL(10,2) NOT NULL,
    currency        CHAR(3),
    min_subtotal_cents BIGINT,
    max_uses        INTEGER,
    used_count      INTEGER      NOT NULL DEFAULT 0,
    valid_from      TIMESTAMPTZ,
    valid_until     TIMESTAMPTZ,
    is_active       BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

-- Saved searches / search alerts
CREATE TABLE saved_searches (
    id              BIGSERIAL PRIMARY KEY,
    user_id         BIGINT       NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name            VARCHAR(120),
    query_params    JSONB        NOT NULL,                       -- location, dates, filters
    alerts_enabled  BOOLEAN      NOT NULL DEFAULT FALSE,
    last_run_at     TIMESTAMPTZ,
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

-- Currency conversion (cached)
CREATE TABLE exchange_rates (
    base_currency   CHAR(3)      NOT NULL,
    quote_currency  CHAR(3)      NOT NULL,
    rate            DECIMAL(18,8) NOT NULL,
    fetched_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    PRIMARY KEY (base_currency, quote_currency, fetched_at)
);


-- =====================================================================
-- 12.5  MEMBERS ENQUIRIES — managed listing program lead capture
-- =====================================================================
-- Captured from the public website + app modal. These are people who own
-- vacation property in points-based programs and want to rent unused
-- inventory through Vaytoven's managed listing program. NOT a self-serve
-- onboarding table — every row triggers a human follow-up by a member
-- specialist, who later may convert the lead into a managed `properties`
-- listing on the platform.

CREATE TABLE members_enquiries (
    id              UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    -- Contact
    first_name      VARCHAR(80)  NOT NULL,
    last_name       VARCHAR(80)  NOT NULL,
    email           CITEXT       NOT NULL,
    phone           VARCHAR(40)  NOT NULL,
    -- Portfolio detail
    program         VARCHAR(120) NOT NULL,           -- e.g. "Marriott Vacation Club", "Disney Vacation Club", "Other / Independent"
    property        TEXT         NOT NULL,           -- free-text: resort name + location (e.g. "Marriott Maui Ocean Club, Lahaina HI")
    annual_points   VARCHAR(40),                     -- free-text on submit; rep validates on call (formats vary across programs)
    best_time_to_call VARCHAR(40),                   -- "Morning (8am–12pm)", "Afternoon (12pm–5pm)", "Evening (5pm–8pm)", "Weekends only"
    notes           TEXT,                            -- "banking restrictions, blackout weeks, multiple memberships, etc."
    -- Consent + provenance
    consent_given   BOOLEAN      NOT NULL DEFAULT FALSE,
    consent_at      TIMESTAMPTZ,                     -- timestamp of explicit consent
    source          VARCHAR(40)  NOT NULL DEFAULT 'website',  -- 'website' | 'app_search' | 'app_host' | 'admin' | 'import'
    referrer_url    TEXT,                            -- where they came from (utm chain etc.)
    user_agent      TEXT,
    ip_address      INET,
    -- Workflow
    status          VARCHAR(30)  NOT NULL DEFAULT 'new'
                    CHECK (status IN ('new','contacted','qualified','onboarded','rejected','unresponsive','duplicate')),
    assigned_to     UUID REFERENCES users(id) ON DELETE SET NULL,  -- the member specialist
    qualified_at    TIMESTAMPTZ,
    onboarded_at    TIMESTAMPTZ,
    rejection_reason TEXT,
    -- Optional link if this enquiry converted into a managed listing
    converted_property_id UUID REFERENCES properties(id) ON DELETE SET NULL,
    -- Audit
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    deleted_at      TIMESTAMPTZ
);

CREATE INDEX idx_members_enquiries_email     ON members_enquiries(email);
CREATE INDEX idx_members_enquiries_status    ON members_enquiries(status) WHERE deleted_at IS NULL;
CREATE INDEX idx_members_enquiries_assigned  ON members_enquiries(assigned_to) WHERE deleted_at IS NULL;
CREATE INDEX idx_members_enquiries_created   ON members_enquiries(created_at DESC);

COMMENT ON TABLE  members_enquiries IS
  'Lead-capture for the managed listing program (points-based vacation properties). Submitted via public marketing site or in-app banner; followed up by a member specialist within 1 business day.';
COMMENT ON COLUMN members_enquiries.program IS
  'Vacation club / points program name, free-text from a curated dropdown. Examples: Marriott Vacation Club, Hilton Grand Vacations, Disney Vacation Club, Wyndham Destinations, RCI Points, Interval International, Other / Independent.';
COMMENT ON COLUMN members_enquiries.converted_property_id IS
  'When set, this lead was successfully onboarded and now has a managed listing in the properties table.';


-- =====================================================================
-- 13. UPDATED_AT TRIGGER (apply to all tables with updated_at)
-- =====================================================================

CREATE OR REPLACE FUNCTION touch_updated_at() RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DO $$
DECLARE t TEXT;
BEGIN
    FOR t IN
        SELECT table_name FROM information_schema.columns
        WHERE column_name = 'updated_at' AND table_schema = 'public'
    LOOP
        EXECUTE format('
            DROP TRIGGER IF EXISTS trg_%1$s_touch ON %1$I;
            CREATE TRIGGER trg_%1$s_touch BEFORE UPDATE ON %1$I
            FOR EACH ROW EXECUTE FUNCTION touch_updated_at();', t);
    END LOOP;
END $$;


-- =====================================================================
-- 14. SEED DATA (minimum to bootstrap)
-- =====================================================================

INSERT INTO property_types (slug, name, sort_order) VALUES
    ('house',     'House',     10),
    ('apartment', 'Apartment', 20),
    ('villa',     'Villa',     30),
    ('cabin',     'Cabin',     40),
    ('condo',     'Condo',     50),
    ('tiny_home', 'Tiny home', 60),
    ('loft',      'Loft',      70),
    ('cottage',   'Cottage',   80);

INSERT INTO amenities (slug, name, category, sort_order) VALUES
    ('wifi',         'Wifi',                'essentials', 10),
    ('kitchen',      'Kitchen',             'essentials', 20),
    ('washer',       'Washer',              'essentials', 30),
    ('dryer',        'Dryer',               'essentials', 40),
    ('ac',           'Air conditioning',    'essentials', 50),
    ('heating',      'Heating',             'essentials', 60),
    ('parking',      'Free parking',        'features',   70),
    ('pool',         'Pool',                'features',   80),
    ('hot_tub',      'Hot tub',             'features',   90),
    ('beachfront',   'Beachfront',          'features',  100),
    ('workspace',    'Dedicated workspace', 'features',  110),
    ('pets',         'Pets allowed',        'features',  120),
    ('smoke_alarm',  'Smoke alarm',         'safety',    130),
    ('co_alarm',     'Carbon monoxide alarm','safety',   140),
    ('first_aid',    'First aid kit',       'safety',    150);


-- =====================================================================
-- NOTES FOR MYSQL 8 PORT
-- =====================================================================
-- * Replace BIGSERIAL with BIGINT AUTO_INCREMENT
-- * Replace SMALLSERIAL with SMALLINT AUTO_INCREMENT
-- * Replace TIMESTAMPTZ with TIMESTAMP (and store UTC at app layer)
-- * Replace CITEXT with VARCHAR(255) + UNIQUE INDEX (lower(email))
-- * Replace INET with VARCHAR(45)
-- * Replace JSONB with JSON
-- * Replace GEOGRAPHY with POINT + SPATIAL INDEX (or store lat/lng only)
-- * Drop the EXCLUDE constraint; enforce no-overlap in application logic
-- * Drop the DO-block trigger setup; create one trigger per table by hand
-- =====================================================================
