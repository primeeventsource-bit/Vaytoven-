<?php

return [
    'default' => env('LOG_CHANNEL', 'stack'),
    'deprecations' => ['channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'), 'trace' => env('LOG_DEPRECATIONS_TRACE', false)],
    'channels' => [
        'stack' => ['driver' => 'stack', 'channels' => explode(',', env('LOG_STACK', 'single')), 'ignore_exceptions' => false],
        'single' => ['driver' => 'single', 'path' => storage_path('logs/laravel.log'), 'level' => env('LOG_LEVEL', 'debug'), 'replace_placeholders' => true],
        'stderr' => [
            'driver'    => 'monolog',
            'level'     => env('LOG_LEVEL', 'debug'),
            'handler'   => Monolog\Handler\StreamHandler::class,
            'with'      => ['stream' => 'php://stderr'],
            // JSON output makes Laravel Cloud's log search queryable by
            // structured fields (level, channel, datetime, context.*).
            // Local dev uses the 'single' channel by default and keeps the
            // human-readable Monolog line format.
            'formatter' => Monolog\Formatter\JsonFormatter::class,
            'processors' => [Monolog\Processor\PsrLogMessageProcessor::class],
        ],
        'null' => ['driver' => 'monolog', 'handler' => Monolog\Handler\NullHandler::class],
        'emergency' => ['path' => storage_path('logs/laravel.log')],
    ],
];
