<?php

declare(strict_types=1);

namespace App\Modules\Documents\Enums;

use Shared\Enums\EnumHelper;

enum DocumentStatus: string
{
    use EnumHelper;

    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case VirusFlagged = 'virus_flagged';
}
