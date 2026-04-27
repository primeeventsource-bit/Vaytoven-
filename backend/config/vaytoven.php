<?php

return [
    /*
    | Platform fees
    | Stored as integer basis points so 14% = 1400. Set in .env so different
    | environments (local, staging, prod) can A/B without code changes.
    */
    'fees' => [
        'guest_bps'       => env('VAYTOVEN_GUEST_FEE_BPS', 1400),
        'host_bps'        => env('VAYTOVEN_HOST_FEE_BPS',   300),
        'default_tax_bps' => env('VAYTOVEN_DEFAULT_TAX_BPS', 850),
    ],

    /*
    | Booking constraints
    */
    'booking' => [
        'max_advance_days'   => 365,
        'min_advance_hours'  => 0,
        'default_min_nights' => 1,
        'default_max_nights' => 28,
    ],

    /*
    | Payouts
    */
    'payouts' => [
        'release_after_hours' => env('VAYTOVEN_PAYOUT_AFTER_HOURS', 24),
        'max_retry_count'     => 3,
        'retry_backoff_min'   => 60,
    ],

    /*
    | Trust & safety
    */
    'trust' => [
        'lockout_after_failed' => 5,
        'lockout_minutes'      => 15,
        'session_inactivity_days' => 30,
    ],
];
