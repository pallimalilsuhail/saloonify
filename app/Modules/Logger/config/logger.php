<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Auto Log Events
    |--------------------------------------------------------------------------
    |
    | When enabled, all events implementing JsonSerializable will be
    | automatically logged to the events log file.
    |
    */
    'auto_log_events' => env('LOGGER_AUTO_LOG_EVENTS', true),

    /*
    |--------------------------------------------------------------------------
    | Excluded Events
    |--------------------------------------------------------------------------
    |
    | Events matching these patterns will not be automatically logged.
    | Use wildcards (*) for pattern matching.
    |
    */
    'excluded_events' => [
        'Illuminate\*',
        'Laravel\*',
        'eloquent.*',
        'bootstrapped:*',
        'bootstrapping:*',
        'creating:*',
        'composing:*',
        'connection.*',
        'cache:*',
    ],
];
