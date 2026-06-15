<?php

declare(strict_types=1);

use AvoqadoDev\UseCase\Middleware\LoggerMiddleware;
use AvoqadoDev\UseCase\Middleware\ReadFromWriteDatabase;
use AvoqadoDev\UseCase\Middleware\WithAtomicLock;
use AvoqadoDev\UseCase\Middleware\WithCache;
use AvoqadoDev\UseCase\Middleware\WithDatabaseTransaction;

return [
    /*
    | Global middleware applied to every use case, in order. A use case
    | opts into transaction wrapping by implementing UsesDatabaseTransaction.
    */
    'middleware' => [
        ReadFromWriteDatabase::class,
        LoggerMiddleware::class,
        WithAtomicLock::class,
        WithCache::class,
        WithDatabaseTransaction::class,
    ],

    'handler_suffix' => 'Handler',

    'logging' => [
        'enabled' => env('USECASE_LOGGING_ENABLED', true),
        'channel' => env('USECASE_LOG_CHANNEL', null),
    ],

    'business_rule_status_code' => 422,
];
