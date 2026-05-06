<?php

return [
    'postmark' => ['token' => env('POSTMARK_TOKEN')],
    'ses' => ['key' => env('AWS_ACCESS_KEY_ID'), 'secret' => env('AWS_SECRET_ACCESS_KEY'), 'region' => env('AWS_DEFAULT_REGION', 'us-east-1')],
    'stripe' => ['secret' => env('STRIPE_SECRET'), 'webhook_secret' => env('STRIPE_WEBHOOK_SECRET')],

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
