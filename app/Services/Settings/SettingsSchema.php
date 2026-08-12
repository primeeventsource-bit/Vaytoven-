<?php

namespace App\Services\Settings;

/**
 * The settings catalog: every admin-tunable key, its group, type, default,
 * validation rules, and visibility. This is the ALLOW-LIST — the repository
 * rejects writes to keys not defined here, and the seeder + admin UI render
 * from it, so adding a key here surfaces it everywhere automatically.
 *
 * Conventions (NFR-1 and friends):
 *   - type 'percent' = whole-number integer percent (12 = 12%). Never floats.
 *   - type 'cents'   = integer cents. The UI converts dollars <-> cents.
 *   - type 'encrypted' values are encrypted at rest; 'sensitive' values are
 *     redacted to '••••' in audit payloads and every API/UI response.
 *   - 'public' keys are exposed through GET /api/v1/settings/public.
 */
final class SettingsSchema
{
    public const GROUPS = [
        'general' => 'General',
        'booking' => 'Booking',
        'fees' => 'Fees',
        'payments' => 'Payments',
        'mlp' => 'Managed Listings',
        'listings' => 'Listings',
        'users' => 'Users',
        'notifications' => 'Notifications',
        'ai_chat' => 'AI Chat',
        'security' => 'Security',
        'integrations' => 'Integrations',
        'seo' => 'SEO',
        'legal' => 'Legal',
    ];

    /** Feature flag keys seeded/managed by this subsystem. */
    public const FEATURE_FLAGS = [
        'instant_book' => 'Allow hosts to enable instant booking (no host approval step).',
        'reviews' => 'Guest reviews and host responses across the site.',
        'wishlists' => 'Traveler wishlists (save properties).',
        'world_map' => 'Interactive world/destination map on browse surfaces.',
        'ai_chat' => 'AI support chat widget and /support/chat endpoint.',
        'mlp_enquiry' => 'Managed Listing Program enquiry form for vacation-property members.',
        'sms_notifications' => 'Outbound SMS notifications (requires an SMS provider).',
        'host_registration' => 'New host sign-ups and host onboarding.',
        'processor.nmi' => 'NMI payment processing.',
        'processor.authorizenet' => 'Authorize.Net payment processing.',
        'processor.nuvei' => 'Nuvei payment processing.',
        'processor.mes' => 'Merchant e-Solutions payment processing.',
        'processor.paymentcloud' => 'PaymentCloud payment processing.',
        'processor.ems' => 'EMS payment processing.',
        'processor.nexio' => 'Nexio payment processing.',
        'processor.netevia' => 'Netevia payment processing.',
        'processor.kurv' => 'Kurv payment processing.',
    ];

    /** Flags seeded disabled (everything else defaults on). */
    public const FLAGS_DEFAULT_OFF = [
        'sms_notifications',
        'processor.authorizenet', 'processor.nuvei', 'processor.mes',
        'processor.paymentcloud', 'processor.ems', 'processor.nexio',
        'processor.netevia', 'processor.kurv',
    ];

    private static ?array $catalog = null;

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::catalog());
    }

    public static function spec(string $key): ?array
    {
        return self::catalog()[$key] ?? null;
    }

    /** @return array<string, array> specs for one group, in sort order */
    public static function group(string $group): array
    {
        return array_filter(self::catalog(), fn (array $spec) => $spec['group'] === $group);
    }

    /** Laravel validation rules for a key (beyond the type cast itself). */
    public static function rules(string $key): array
    {
        return self::catalog()[$key]['rules'] ?? [];
    }

    public static function defaultFor(string $key): mixed
    {
        return self::catalog()[$key]['default'] ?? null;
    }

    /**
     * key => spec. Spec keys: group, type, default, label, rules, help,
     * public, sensitive, options (enum only). Omitted booleans are false.
     */
    public static function catalog(): array
    {
        return self::$catalog ??= self::build();
    }

    private static function build(): array
    {
        $defs = [
            // ---------------------------------------------------------- general
            'general.site_name' => ['general', 'string', 'Vaytoven Rentals', 'Site name', ['string', 'max:120'], 'Shown in titles, emails, and receipts.', true],
            'general.tagline' => ['general', 'string', 'Find your place anywhere.', 'Tagline', ['nullable', 'string', 'max:160'], null, true],
            'general.logo_url' => ['general', 'string', '', 'Logo URL', ['nullable', 'string', 'max:512'], null, true],
            'general.favicon_url' => ['general', 'string', '', 'Favicon URL', ['nullable', 'string', 'max:512'], null, true],
            'general.support_email' => ['general', 'string', 'support@vaytoven.com', 'Support email', ['nullable', 'email', 'max:255'], null, true],
            'general.support_phone' => ['general', 'string', '', 'Support phone', ['nullable', 'string', 'max:32'], null, true],
            'general.company_legal_name' => ['general', 'string', 'Vaytoven Technologies LLC', 'Company legal name', ['string', 'max:160']],
            'general.timezone' => ['general', 'string', 'America/New_York', 'Timezone', ['timezone:all']],
            'general.default_locale' => ['general', 'enum', 'en', 'Default locale', ['in:en'], null, false, false, ['en']],
            'general.default_currency' => ['general', 'enum', 'USD', 'Default currency', ['in:USD,EUR,GBP,CAD,MXN'], null, false, false, ['USD', 'EUR', 'GBP', 'CAD', 'MXN']],
            'general.maintenance_mode' => ['general', 'bool', false, 'Maintenance mode', ['boolean'], 'Non-admin visitors see the maintenance message; admins keep full access.', true],
            'general.maintenance_message' => ['general', 'text', 'We are briefly down for maintenance and will be right back.', 'Maintenance message', ['nullable', 'string', 'max:2000'], null, true],
            'general.confirmation_code_prefix' => ['general', 'string', 'VYT-', 'Confirmation code prefix', ['string', 'max:8'], 'Display/validation only — code generation stays server-authoritative.'],
            'general.social_instagram' => ['general', 'string', '', 'Instagram URL', ['nullable', 'string', 'max:255'], null, true],
            'general.social_facebook' => ['general', 'string', '', 'Facebook URL', ['nullable', 'string', 'max:255'], null, true],
            'general.social_tiktok' => ['general', 'string', '', 'TikTok URL', ['nullable', 'string', 'max:255'], null, true],
            'general.social_x' => ['general', 'string', '', 'X (Twitter) URL', ['nullable', 'string', 'max:255'], null, true],
            'general.social_youtube' => ['general', 'string', '', 'YouTube URL', ['nullable', 'string', 'max:255'], null, true],

            // ---------------------------------------------------------- booking
            'booking.stay_checkout_enabled' => ['booking', 'bool', false, 'Enable stay checkout (charges guests for rentals)', ['boolean'], 'OFF by default. Vaytoven advertises listings and does not collect rental funds or process payments between travelers and owners — visitors use Submit Offer. Only enable under a written arrangement that makes Vaytoven a party to the rental transaction.'],
            'booking.min_nights' => ['booking', 'int', 1, 'Minimum nights (site-wide floor)', ['integer', 'min:1', 'max:30'], 'Hosts can require more per property, never less.'],
            'booking.max_nights' => ['booking', 'int', 30, 'Maximum nights', ['integer', 'min:1', 'max:365'], 'v1 caps long-term stays at 30 nights.'],
            'booking.advance_window_days' => ['booking', 'int', 365, 'Advance booking window (days)', ['integer', 'min:1', 'max:1095'], 'How far in the future check-in may be.'],
            'booking.checkin_time' => ['booking', 'string', '15:00', 'Default check-in time', ['date_format:H:i']],
            'booking.checkout_time' => ['booking', 'string', '11:00', 'Default check-out time', ['date_format:H:i']],
            'booking.instant_book_default' => ['booking', 'bool', false, 'Instant book on by default for new listings', ['boolean']],
            'booking.default_cancellation_policy' => ['booking', 'enum', 'moderate', 'Default cancellation policy', ['in:flexible,moderate,strict,non_refundable'], null, false, false, ['flexible', 'moderate', 'strict', 'non_refundable']],
            'booking.allow_same_day' => ['booking', 'bool', true, 'Allow same-day bookings', ['boolean']],
            'booking.hold_minutes' => ['booking', 'int', 15, 'Checkout hold (minutes)', ['integer', 'min:5', 'max:120'], 'How long a pending checkout reserves the dates.'],

            // ---------------------------------------------------------- fees
            'fees.active_schedule_id' => ['fees', 'int', 0, 'Active fee schedule', ['integer', 'min:0'], '0 = use the scalar percentages below.'],
            'fees.guest_service_pct' => ['fees', 'percent', 12, 'Guest service fee %', ['integer', 'between:0,50'], 'Applied to the nightly subtotal on every quote.'],
            'fees.host_commission_pct' => ['fees', 'percent', 3, 'Host commission %', ['integer', 'between:0,50']],
            'fees.payout_delay_days' => ['fees', 'int', 1, 'Payout delay after checkout (days)', ['integer', 'between:0,30']],
            'fees.payout_schedule' => ['fees', 'enum', 'on_checkout', 'Payout schedule', ['in:daily,weekly,on_checkout'], null, false, false, ['daily', 'weekly', 'on_checkout']],
            'fees.security_deposit_default_cents' => ['fees', 'cents', 0, 'Default security deposit', ['integer', 'min:0']],
            'fees.tax_inclusive_display' => ['fees', 'bool', false, 'Show tax-inclusive prices', ['boolean'], null, true],

            // ---------------------------------------------------------- payments
            'payments.default_processor' => ['payments', 'enum', 'nmi', 'Default processor', ['in:nmi,authorizenet,nuvei,mes,paymentcloud,ems,nexio,netevia,kurv'], null, false, false, ['nmi', 'authorizenet', 'nuvei', 'mes', 'paymentcloud', 'ems', 'nexio', 'netevia', 'kurv']],
            'payments.routing_strategy' => ['payments', 'enum', 'priority', 'Routing strategy', ['in:priority,round_robin,amount_based'], null, false, false, ['priority', 'round_robin', 'amount_based']],
            'payments.retry_failed' => ['payments', 'bool', true, 'Retry failed charges', ['boolean']],
            'payments.retry_max_attempts' => ['payments', 'int', 2, 'Max retry attempts', ['integer', 'between:0,5']],
            'payments.statement_descriptor' => ['payments', 'string', 'VAYTOVEN', 'Statement descriptor', ['string', 'max:22', 'regex:/^[A-Z0-9 .\-*]+$/'], 'Card networks cap this at 22 characters.'],

            // ---------------------------------------------------------- mlp
            'mlp.enquiry_form_enabled' => ['mlp', 'bool', true, 'Enquiry form enabled', ['boolean'], null, true],
            'mlp.auto_response_template' => ['mlp', 'string', 'mlp_auto_response', 'Auto-response template key', ['nullable', 'string', 'max:96']],
            'mlp.slack_webhook_url' => ['mlp', 'encrypted', '', 'Slack webhook URL', ['nullable', 'string', 'max:512'], 'Overrides SLACK_OPS_WEBHOOK_URL when set.', false, true],
            'mlp.specialist_assignment' => ['mlp', 'enum', 'round_robin', 'Specialist assignment', ['in:round_robin,manual,load_balanced'], null, false, false, ['round_robin', 'manual', 'load_balanced']],
            'mlp.sla_first_response_hours' => ['mlp', 'int', 4, 'First-response SLA (hours)', ['integer', 'between:1,72']],
            'mlp.enquiry_required_fields' => ['mlp', 'json', ['name', 'email', 'phone', 'program'], 'Required enquiry fields', ['array']],
            'mlp.confirmation_email_enabled' => ['mlp', 'bool', true, 'Send confirmation email', ['boolean']],

            // ---------------------------------------------------------- listings
            'listings.approval_mode' => ['listings', 'enum', 'manual', 'Listing approval', ['in:auto,manual,first_only'], 'first_only reviews a host\'s first listing manually, then auto-approves.', false, false, ['auto', 'manual', 'first_only']],
            'listings.min_photos' => ['listings', 'int', 4, 'Minimum photos', ['integer', 'between:0,30']],
            'listings.max_photos' => ['listings', 'int', 30, 'Maximum photos', ['integer', 'between:1,100']],
            'listings.require_address_verification' => ['listings', 'bool', true, 'Require address verification', ['boolean']],
            'listings.default_property_type' => ['listings', 'string', 'house', 'Default property type slug', ['nullable', 'string', 'max:64']],
            'listings.allow_host_pricing_override' => ['listings', 'bool', true, 'Hosts may override suggested pricing', ['boolean']],

            // ---------------------------------------------------------- users
            'users.registration_open' => ['users', 'bool', true, 'Open registration', ['boolean'], null, true],
            'users.require_email_verification' => ['users', 'bool', true, 'Require email verification', ['boolean']],
            'users.host_onboarding_required' => ['users', 'bool', true, 'Host onboarding required before listing', ['boolean']],
            'users.host_requires_approval' => ['users', 'bool', true, 'New hosts require ops approval', ['boolean']],
            'users.min_password_length' => ['users', 'int', 12, 'Minimum password length', ['integer', 'between:8,128']],
            'users.allow_social_login' => ['users', 'bool', false, 'Allow social login', ['boolean']],

            // ---------------------------------------------------------- notifications
            'notifications.from_name' => ['notifications', 'string', 'Vaytoven Rentals', 'From name', ['string', 'max:120']],
            'notifications.from_email' => ['notifications', 'string', 'no-reply@vaytoven.com', 'From email', ['email', 'max:255']],
            'notifications.email_enabled' => ['notifications', 'bool', true, 'Email notifications', ['boolean']],
            'notifications.sms_enabled' => ['notifications', 'bool', false, 'SMS notifications', ['boolean'], 'Also requires the sms_notifications feature flag and a provider.'],
            'notifications.push_enabled' => ['notifications', 'bool', true, 'Push notifications', ['boolean']],
            'notifications.booking_confirmation_template' => ['notifications', 'string', 'booking_confirmation', 'Booking confirmation template key', ['nullable', 'string', 'max:96']],
            'notifications.retention_days' => ['notifications', 'int', 90, 'In-app notification retention (days)', ['integer', 'between:7,3650']],
            'notifications.channel_matrix' => ['notifications', 'json', [
                'booking_confirmed' => ['email'],
                'booking_cancelled' => ['email'],
                'payout_sent' => ['email'],
                'enquiry_received' => ['email'],
            ], 'Channel matrix', ['array'], 'Per event: which of email/sms/push fire.'],

            // ---------------------------------------------------------- ai_chat
            'ai_chat.enabled' => ['ai_chat', 'bool', true, 'AI chat enabled', ['boolean'], 'Also gated by the ai_chat feature flag.', true],
            'ai_chat.model' => ['ai_chat', 'string', 'claude-sonnet-4-6', 'Model', ['string', 'max:64']],
            'ai_chat.system_prompt' => ['ai_chat', 'text', '', 'System prompt override', ['nullable', 'string', 'max:20000'], 'Blank = the built-in support prompt.'],
            'ai_chat.max_turns_before_handoff' => ['ai_chat', 'int', 6, 'Max turns before human handoff', ['integer', 'between:1,50']],
            'ai_chat.business_hours_only' => ['ai_chat', 'bool', false, 'Business hours only', ['boolean']],
            'ai_chat.handoff_queue' => ['ai_chat', 'enum', 'support', 'Handoff queue', ['in:support,member_specialist'], null, false, false, ['support', 'member_specialist']],
            'ai_chat.rate_limit_per_session' => ['ai_chat', 'int', 40, 'Messages per session', ['integer', 'between:1,500']],
            'ai_chat.api_key' => ['ai_chat', 'encrypted', '', 'Anthropic API key override', ['nullable', 'string', 'max:255'], 'Blank = use ANTHROPIC_API_KEY from the environment.', false, true],

            // ---------------------------------------------------------- security
            'security.session_timeout_min' => ['security', 'int', 120, 'Session timeout (minutes)', ['integer', 'between:5,1440']],
            'security.admin_2fa_required' => ['security', 'bool', true, 'Require 2FA for admins', ['boolean']],
            'security.admin_ip_allowlist' => ['security', 'json', [], 'Admin IP allowlist', ['array'], 'Empty = allow all.'],
            'security.login_anomaly_threshold' => ['security', 'int', 3, 'Login anomaly threshold', ['integer', 'between:1,20'], 'Failed attempts before an account is flagged.'],
            'security.geoip_provider' => ['security', 'enum', 'maxmind', 'GeoIP provider', ['in:maxmind,ipapi'], null, false, false, ['maxmind', 'ipapi']],
            'security.tracking_evidence_min_weight' => ['security', 'int', 40, 'Tracking evidence minimum weight', ['integer', 'between:0,100'], 'Threshold on the 0–100 evidence scale for chargeback packets.'],
            'security.terms_reaccept_on_new_version' => ['security', 'bool', true, 'Force re-acceptance on new terms version', ['boolean']],
            'security.password_reset_ttl_min' => ['security', 'int', 60, 'Password reset link TTL (minutes)', ['integer', 'between:10,1440']],

            // ---------------------------------------------------------- integrations
            'integrations.mail_transport' => ['integrations', 'enum', 'smtp', 'Mail transport', ['in:smtp,ses,postmark,resend'], null, false, false, ['smtp', 'ses', 'postmark', 'resend']],
            'integrations.smtp_host' => ['integrations', 'string', '', 'SMTP host', ['nullable', 'string', 'max:255']],
            'integrations.smtp_port' => ['integrations', 'int', 587, 'SMTP port', ['integer', 'between:1,65535']],
            'integrations.smtp_username' => ['integrations', 'string', '', 'SMTP username', ['nullable', 'string', 'max:255']],
            'integrations.smtp_password' => ['integrations', 'encrypted', '', 'SMTP password', ['nullable', 'string', 'max:255'], null, false, true],
            'integrations.sms_provider' => ['integrations', 'enum', 'none', 'SMS provider', ['in:twilio,none'], null, false, false, ['twilio', 'none']],
            'integrations.twilio_sid' => ['integrations', 'string', '', 'Twilio SID', ['nullable', 'string', 'max:64']],
            'integrations.twilio_token' => ['integrations', 'encrypted', '', 'Twilio auth token', ['nullable', 'string', 'max:255'], null, false, true],
            'integrations.maxmind_license_key' => ['integrations', 'encrypted', '', 'MaxMind license key', ['nullable', 'string', 'max:255'], null, false, true],
            'integrations.analytics_id' => ['integrations', 'string', '', 'Analytics ID', ['nullable', 'string', 'max:64'], null, true],
            'integrations.slack_alerts_webhook' => ['integrations', 'encrypted', '', 'Slack alerts webhook', ['nullable', 'string', 'max:512'], null, false, true],

            // ---------------------------------------------------------- seo
            'seo.meta_title_default' => ['seo', 'string', 'Vaytoven Rentals — vacation homes, villas & member stays', 'Default meta title', ['string', 'max:160'], null, true],
            'seo.meta_description_default' => ['seo', 'text', 'Book vacation homes and villas, or put your vacation-property membership to work with Vaytoven\'s Managed Listing Program.', 'Default meta description', ['string', 'max:320'], null, true],
            'seo.og_image_url' => ['seo', 'string', '', 'Open Graph image URL', ['nullable', 'string', 'max:512'], null, true],
            'seo.robots_index' => ['seo', 'bool', true, 'Allow search indexing', ['boolean'], null, true],
            'seo.sitemap_enabled' => ['seo', 'bool', true, 'Sitemap enabled', ['boolean']],
            'seo.featured_destinations' => ['seo', 'json', [], 'Featured destinations', ['array'], null, true],

            // ---------------------------------------------------------- legal
            'legal.terms_version_id' => ['legal', 'int', 0, 'Pinned terms version id', ['integer', 'min:0'], '0 = latest effective version from terms_versions.'],
            'legal.privacy_url' => ['legal', 'string', '/legal/privacy', 'Privacy policy URL', ['string', 'max:512'], null, true],
            'legal.cancellation_terms_template' => ['legal', 'string', '', 'Cancellation terms template key', ['nullable', 'string', 'max:96']],
            'legal.dispute_window_days' => ['legal', 'int', 60, 'Dispute window (days)', ['integer', 'between:1,365']],
        ];

        $catalog = [];
        $sort = 0;
        foreach ($defs as $key => $def) {
            $catalog[$key] = [
                'group' => $def[0],
                'type' => $def[1],
                'default' => $def[2],
                'label' => $def[3],
                'rules' => $def[4] ?? [],
                'help' => $def[5] ?? null,
                'public' => $def[6] ?? false,
                'sensitive' => ($def[7] ?? false) || $def[1] === 'encrypted',
                'options' => $def[8] ?? null,
                'sort' => $sort += 10,
            ];
        }

        return $catalog;
    }
}
