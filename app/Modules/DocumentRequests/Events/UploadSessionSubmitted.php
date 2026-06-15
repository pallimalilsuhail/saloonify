<?php

declare(strict_types=1);

namespace App\Modules\DocumentRequests\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Shared\ValueObjects\Id;

final class UploadSessionSubmitted
{
    use Dispatchable;

    /**
     * @param  array<int, Id>  $documentIds  ids of every document confirmed in this submission
     */
    public function __construct(
        public readonly Id $sessionId,
        public readonly Id $businessId,
        public readonly Id $customerId,
        public readonly array $documentIds,
    ) {}
}
