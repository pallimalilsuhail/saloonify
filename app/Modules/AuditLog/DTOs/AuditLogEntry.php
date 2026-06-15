<?php

declare(strict_types=1);

namespace App\Modules\AuditLog\DTOs;

use App\Modules\AuditLog\Models\AuditLog;
use Carbon\CarbonImmutable;
use Shared\ValueObjects\Id;

/**
 * Read-side projection of an audit log row for the viewer page + CSV
 * export. Eager-resolves actor name/email so the table doesn't N+1.
 */
final readonly class AuditLogEntry
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function __construct(
        public Id $id,
        public string $action,
        public string $actorType,
        public ?string $actorName,
        public ?string $actorEmail,
        public ?string $targetType,
        public ?string $targetId,
        public ?string $ip,
        public ?array $meta,
        public CarbonImmutable $createdAt,
    ) {}

    public static function fromModel(AuditLog $log): self
    {
        $actor = $log->actor;

        return new self(
            id: Id::fromString($log->ulid),
            action: $log->action,
            actorType: $log->actor_type,
            actorName: $actor?->name,
            actorEmail: $actor?->email,
            targetType: $log->target_type,
            targetId: $log->target_id,
            ip: $log->ip,
            meta: $log->meta,
            createdAt: $log->created_at->toImmutable(),
        );
    }
}
