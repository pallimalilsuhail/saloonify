<?php

declare(strict_types=1);

namespace App\Modules\Businesses\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case BusinessAdmin = 'business_admin';
    case LocationAgent = 'location_agent';

    public function is(self $role): bool
    {
        return $this === $role;
    }
}
