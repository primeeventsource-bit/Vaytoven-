<?php

return [
    'name' => env('APP_NAME', 'Vaytoven Rentals'),
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
