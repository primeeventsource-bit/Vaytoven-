<?php

return [
    // The consumer brand is "Vaytoven". "Vaytoven Rentals" was an early
    // framing that was dropped, and it was still heading every email this
    // application sent wherever APP_NAME was unset.
    'name' => env('APP_NAME', 'Vaytoven'),

    /*
    | The registered company, as distinct from the consumer brand above.
    | "Vaytoven" is what marketing copy says; "VAYTOVEN Technologies LLC"
    | is what identifies the sender on an email and what belongs on
    | anything legal. Kept here so the two cannot drift apart.
    */
    'legal_entity' => env('APP_LEGAL_ENTITY', 'VAYTOVEN Technologies LLC'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'https://www.vaytoven.com'),
    // Storage stays UTC. Changing this would reinterpret every existing
    // timestamp - see the et() helper for why that is not a one-line change.
    'timezone' => env('APP_TIMEZONE', 'UTC'),

    // What the site SHOWS. Eastern, following daylight saving, so times
    // read the way the business reads them.
    'display_timezone' => env('APP_DISPLAY_TIMEZONE', 'America/New_York'),
    'locale' => env('APP_LOCALE', 'en'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => [...array_filter(explode(',', env('APP_PREVIOUS_KEYS', '')))],
    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],
];
