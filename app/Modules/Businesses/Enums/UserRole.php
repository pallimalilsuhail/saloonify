<?php

declare(strict_types=1);

namespace App\Modules\Businesses\Enums;

use Shared\Enums\EnumHelper;

enum UserRole: string
{
    use EnumHelper;

    case SuperAdmin = 'super_admin';
    case Owner = 'owner';
    case Member = 'member';
}
