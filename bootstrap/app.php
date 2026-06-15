<?php

declare(strict_types=1);

use App\Modules\Businesses\Http\Middleware\EnsureOwner;
use App\Modules\Businesses\Http\Middleware\EnsureSuperAdmin;
use App\Modules\Common\Http\Middleware\EnsureBusinessMember;
use App\Modules\Logger\Middleware\RequestTracingMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(RequestTracingMiddleware::class);
        $middleware->alias([
            'super_admin' => EnsureSuperAdmin::class,
            'owner' => EnsureOwner::class,
            'business_member' => EnsureBusinessMember::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);
    })->create();
