<?php

declare(strict_types=1);

namespace App\Modules\Documents\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Shared\ValueObjects\Id;

final class DocumentDeleted
{
    use Dispatchable;

    public function __construct(
        public readonly Id $documentId,
        public readonly Id $businessId,
        public readonly Id $actorId,
    ) {}
}
