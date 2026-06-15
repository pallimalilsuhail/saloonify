<?php

declare(strict_types=1);

namespace App\Modules\AuditLog\UseCases\ListAuditLogs;

use App\Modules\AuditLog\DTOs\AuditLogEntry;
use AvoqadoDev\UseCase\Contracts\Request;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Shared\ValueObjects\Id;

/**
 * @see ListAuditLogsHandler
 *
 * Returns a paginator of {@see AuditLogEntry}.
 *
 * @implements Request<LengthAwarePaginator>
 */
final readonly class ListAuditLogs implements Request
{
    public function __construct(
        public Id $businessId,
        public ?Id $actorId = null,
        public ?string $action = null,
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $to = null,
        public int $perPage = 25,
    ) {}
}
