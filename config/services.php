<?php

return [
    'postmark' => ['token' => env('POSTMARK_TOKEN')],
    'ses' => ['key' => env('AWS_ACCESS_KEY_ID'), 'secret' => env('AWS_SECRET_ACCESS_KEY'), 'region' => env('AWS_DEFAULT_REGION', 'us-east-1')],
    // NMI payment gateway (replaced Stripe, 2026-07). Collect.js tokenizes
    // the card in the browser with the PUBLIC tokenization_key; the private
    // security_key authenticates server-side Payment API calls (sale, refund,
    // Customer Vault). Both must be set or the booking flow stays in demo
    // mode. webhook_signing_key comes from the merchant portal when the
    // webhook endpoint is registered.
    'nmi' => [
        'security_key'        => env('NMI_SECURITY_KEY'),
        'tokenization_key'    => env('NMI_TOKENIZATION_KEY'),
        'webhook_signing_key' => env('NMI_WEBHOOK_SIGNING_KEY'),
        'endpoint'            => env('NMI_ENDPOINT', 'https://secure.nmi.com/api/transact.php'),
        'collect_js_url'      => env('NMI_COLLECT_JS_URL', 'https://secure.nmi.com/token/Collect.js'),
    ],

    // Anthropic Claude — AI Support Chat (Phase 7). When ANTHROPIC_API_KEY is
    // unset the controller returns 503 with a generic graceful fallback message.
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model'   => env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
    ],

    // MaxMind GeoIP2 (Phase 9). City db is required for country/city/lat-lng;
    // anonymous db (separate file) adds VPN/Tor/datacenter detection. Either
    // mount the .mmdb files via the deploy filesystem and set the absolute
    // path here, or leave both unset to fall back to NoOpGeoIpService.
    'maxmind' => [
        'mmdb_path'           => env('MAXMIND_MMDB_PATH'),
        'anonymous_mmdb_path' => env('MAXMIND_ANONYMOUS_MMDB_PATH'),
    ],

    // Mapbox — used to render the dashboard map tiles (FR-12.x analytics).
    // Token comes from env MAPBOX_API (set on every Cloud env, including
    // sandbox + dev). If unset, listing-analytics.blade.php falls back to
    // raw OpenStreetMap tiles so the map still renders.
    //
    // Style accepts Mapbox style URLs in the form "mapbox/<style>" — common
    // values: light-v11 (default, matches the brand cream palette),
    // streets-v12, dark-v11, satellite-streets-v12.
    'mapbox' => [
        'token' => env('MAPBOX_API'),
        'style' => env('MAPBOX_STYLE', 'mapbox/light-v11'),
    ],

    // Per-processor chargeback rebuttal portal URLs (Phase 12). Each processor
    // adapter falls back to a sane default if the env var is unset; override to
    // route ops to a sandbox/staging portal in non-production environments.
    'chargeback' => [
        'authorizenet' => ['portal_url' => env('CHARGEBACK_PORTAL_AUTHORIZENET')],
        'nmi'          => ['portal_url' => env('CHARGEBACK_PORTAL_NMI')],
        'nuvei'        => ['portal_url' => env('CHARGEBACK_PORTAL_NUVEI')],
        'mes'          => ['portal_url' => env('CHARGEBACK_PORTAL_MES')],
        'paymentcloud' => ['portal_url' => env('CHARGEBACK_PORTAL_PAYMENTCLOUD')],
        'ems'          => ['portal_url' => env('CHARGEBACK_PORTAL_EMS')],
        'nexio'        => ['portal_url' => env('CHARGEBACK_PORTAL_NEXIO')],
        'netevia'      => ['portal_url' => env('CHARGEBACK_PORTAL_NETEVIA')],
        'kurv'         => ['portal_url' => env('CHARGEBACK_PORTAL_KURV')],
    ],

    // Slack incoming webhook for ops notifications (member enquiries, etc.).
    // Leave SLACK_OPS_WEBHOOK_URL unset and the SlackNotifier binding falls
    // back to NoOpSlackNotifier so jobs run cleanly in dev and CI.
    'slack' => [
        'ops_webhook_url' => env('SLACK_OPS_WEBHOOK_URL'),
    ],

    'docusign' => [
        // Switch oauth_base + api_base to https://account.docusign.com /
        // https://www.docusign.net for production. Defaults are demo / sandbox.
        'oauth_base'        => env('DOCUSIGN_OAUTH_BASE', 'https://account-d.docusign.com'),
        'api_base'          => env('DOCUSIGN_API_BASE',   'https://demo.docusign.net'),
        'integration_key'   => env('DOCUSIGN_INTEGRATION_KEY'),
        'user_id'           => env('DOCUSIGN_USER_ID'),
        'account_id'        => env('DOCUSIGN_ACCOUNT_ID'),
        // Provide one of:
        //   DOCUSIGN_PRIVATE_KEY (full PEM string, with literal \n preserved)
        //   DOCUSIGN_PRIVATE_KEY_PATH (absolute path to PEM file on disk)
        'private_key'       => env('DOCUSIGN_PRIVATE_KEY'),
        'private_key_path'  => env('DOCUSIGN_PRIVATE_KEY_PATH'),
        // Pipe-separated list of HMAC keys configured on the Connect listener.
        // Up to 10 may be active at once during key rotation.
        'hmac_keys'         => array_filter(explode('|', (string) env('DOCUSIGN_HMAC_KEYS'))),
    ],
];
