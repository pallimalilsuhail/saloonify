<?php

declare(strict_types=1);

namespace App\Modules\Businesses\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case OnLeave = 'on_leave';
    case Terminated = 'terminated';

    public function is(self $status): bool
    {
        return $this === $status;
    }
}
