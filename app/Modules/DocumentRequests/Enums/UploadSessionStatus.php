<?php

declare(strict_types=1);

namespace App\Modules\DocumentRequests\Enums;

use Shared\Enums\EnumHelper;

enum UploadSessionStatus: string
{
    use EnumHelper;

    case Active = 'active';
    case Submitted = 'submitted';
    case Expired = 'expired';
    case Revoked = 'revoked';
}
