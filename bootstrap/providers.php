<?php

use App\Modules\AuditLog\AuditLogServiceProvider;
use App\Modules\Logger\LoggerServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\TelescopeServiceProvider;
use App\Providers\VoltServiceProvider;

return [
    LoggerServiceProvider::class,
    AuditLogServiceProvider::class,
    AppServiceProvider::class,
    TelescopeServiceProvider::class,
    VoltServiceProvider::class,
];
