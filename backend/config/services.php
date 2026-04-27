<?php

return [
    'mailgun' => [
        'domain'   => env('MAILGUN_DOMAIN'),
        'secret'   => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'stripe' => [
        'key'                => env('STRIPE_KEY'),
        'secret'             => env('STRIPE_SECRET'),
        'webhook_secret'     => env('STRIPE_WEBHOOK_SECRET'),
        'connect_client_id'  => env('STRIPE_CONNECT_CLIENT_ID'),
    ],

    'twilio' => [
        'sid'   => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
        'from'  => env('TWILIO_FROM'),
    ],

    'maxmind' => [
        'license_key' => env('MAXMIND_LICENSE_KEY'),
        'db_path'     => env('MAXMIND_DB_PATH', '/var/lib/geoip/GeoLite2-City.mmdb'),
    ],

    'slack' => [
        'members_webhook' => env('SLACK_MEMBERS_WEBHOOK'),
    ],
];
