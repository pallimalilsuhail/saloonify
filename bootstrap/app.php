<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureUserIsBusinessAdmin;
use App\Http\Middleware\EnsureUserIsLocationAgent;
use App\Http\Middleware\EnsureUserIsSuperAdmin;
use App\Http\Middleware\TenantContext;
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

        // Bind tenant context after the session/auth middleware on web.
        $middleware->appendToGroup('web', TenantContext::class);

        $middleware->alias([
            'super_admin' => EnsureUserIsSuperAdmin::class,
            'business_admin' => EnsureUserIsBusinessAdmin::class,
            'location_agent' => EnsureUserIsLocationAgent::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);
    })->create();
