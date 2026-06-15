<?php

declare(strict_types=1);

namespace App\Modules\AuditLog;

use App\Modules\AuditLog\Listeners\AuditEventSubscriber;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class AuditLogServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::subscribe(AuditEventSubscriber::class);
    }
}
