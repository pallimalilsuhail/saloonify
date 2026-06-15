<?php

declare(strict_types=1);

namespace App\Modules\AuditLog\UseCases\RecordAuditEvent;

use AvoqadoDev\UseCase\Contracts\Request;
use Shared\ValueObjects\Id;

/**
 * @see RecordAuditEventHandler
 *
 * @implements Request<Id>
 */
final readonly class RecordAuditEvent implements Request
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function __construct(
        public string $action,
        public string $actorType,
        public ?Id $businessId = null,
        public ?Id $actorId = null,
        public ?string $targetType = null,
        public ?string $targetId = null,
        public ?string $ip = null,
        public ?string $userAgent = null,
        public ?array $meta = null,
    ) {}
}
