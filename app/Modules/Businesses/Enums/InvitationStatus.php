<?php

declare(strict_types=1);

namespace App\Modules\Businesses\Enums;

use Shared\Enums\EnumHelper;

enum InvitationStatus: string
{
    use EnumHelper;

    case Pending = 'pending';
    case Accepted = 'accepted';
    case Revoked = 'revoked';
    case Expired = 'expired';
}
