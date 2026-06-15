<?php

declare(strict_types=1);

namespace App\Modules\Businesses\Enums;

use Shared\Enums\EnumHelper;

enum BusinessStatus: string
{
    use EnumHelper;

    case Active = 'active';
    case Suspended = 'suspended';
}
