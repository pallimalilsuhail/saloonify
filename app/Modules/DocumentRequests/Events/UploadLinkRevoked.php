<?php

declare(strict_types=1);

namespace App\Modules\DocumentRequests\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Shared\ValueObjects\Id;

final class UploadLinkRevoked
{
    use Dispatchable;

    public function __construct(
        public readonly Id $sessionId,
        public readonly Id $businessId,
        public readonly Id $customerId,
        public readonly ?Id $revokedById,
    ) {}
}
