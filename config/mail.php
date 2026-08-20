<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default mailer
    |--------------------------------------------------------------------------
    |
    | The default is deliberately 'log' so a developer who has not configured
    | anything writes mail to storage/logs instead of accidentally emailing
    | real people from a test database.
    |
    | That default is also how this application spent its first months in
    | production: MAIL_MAILER was never set on any Laravel Cloud environment,
    | so every password reset was written to a log file while the site told the
    | user their reset link was on its way. See the 'log' guard in
    | App\Support\Mail\MailDeliverability — a log mailer outside local
    | development is treated as an outage, not a configuration.
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    'mailers' => [

        // Works with every transactional provider (Resend, Postmark, SES,
        // Mailgun, SendGrid, Google Workspace) without adding a dependency.
        // MAIL_SCHEME=smtps selects implicit TLS on port 465; leave it unset
        // for STARTTLS on 587, which is what most providers document.
        'smtp' => [
            'transport'    => 'smtp',
            'scheme'       => env('MAIL_SCHEME'),
            'url'          => env('MAIL_URL'),
            'host'         => env('MAIL_HOST', '127.0.0.1'),
            'port'         => env('MAIL_PORT', 587),
            'username'     => env('MAIL_USERNAME'),
            // Resend authenticates with the API key AS the SMTP password. Read
            // from the key variable when no explicit MAIL_PASSWORD is set, so the
            // secret lives in exactly one place - copying it into a second
            // variable means the two drift the first time it is rotated, and the
            // one that is wrong fails silently as an auth error.
            'password'     => env('MAIL_PASSWORD') ?: env('RESEND_API_KEY', env('resend_api')),
            'timeout'      => 15,
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path'      => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel'   => env('MAIL_LOG_CHANNEL'),
        ],

        // Used by the test suite via Mail::fake(); also a safe way to disable
        // outbound mail deliberately rather than by forgetting to configure it.
        'array' => [
            'transport' => 'array',
        ],

        // Keeps password resets working through a provider incident: if the
        // primary refuses the message, Symfony tries the next transport.
        // Point MAIL_MAILER at this once a secondary is configured.
        'failover' => [
            'transport' => 'failover',
            'mailers'   => ['smtp', 'sendmail'],
            'retry_after' => 60,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "from" address
    |--------------------------------------------------------------------------
    |
    | contact@vaytoven.com rather than a no-reply: it is the company's real
    | monitored inbox, so a user who hits reply on a password-reset mail
    | reaches a human instead of a black hole. MAIL_FROM_ADDRESS must be on a
    | domain the sending provider is authorised for (SPF/DKIM), or the mail
    | will be dropped or spam-foldered no matter how correct the config is.
    |
    */

    /*
    | The office inbox. Internal notifications go here and only here -
    | never to the member the notification is about.
    */
    'office_address' => env('MAIL_OFFICE_ADDRESS', 'contact@vaytoven.com'),

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'contact@vaytoven.com'),
        'name'    => env('MAIL_FROM_NAME', 'Vaytoven'),
    ],

];
