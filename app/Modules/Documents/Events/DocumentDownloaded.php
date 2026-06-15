<?php

declare(strict_types=1);

namespace App\Modules\Documents\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Shared\ValueObjects\Id;

/**
 * Fired every time a force-download URL is issued. Distinct from
 * DocumentViewUrlIssued so the audit log can tell intent apart
 * (read vs save-to-disk).
 */
final class DocumentDownloaded
{
    use Dispatchable;

    public function __construct(
        public readonly Id $documentId,
        public readonly Id $businessId,
        public readonly Id $actorId,
    ) {}
}
