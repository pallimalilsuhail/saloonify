<?php

declare(strict_types=1);

namespace App\Modules\AuditLog\Enums;

use Shared\Enums\EnumHelper;

enum ActorType: string
{
    use EnumHelper;

    case User = 'user';
    case Anonymous = 'anonymous';
    case System = 'system';
}
