<?php

declare(strict_types=1);

namespace App\Modules\AuditLog\UseCases\ExportAuditLogsCsv;

use AvoqadoDev\UseCase\Contracts\Request;
use Carbon\CarbonImmutable;
use Shared\ValueObjects\Id;

/**
 * @see ExportAuditLogsCsvHandler
 *
 * Returns the rendered CSV body as a string. The controller wraps it
 * in a streamed response so very large exports don't OOM the process.
 *
 * @implements Request<string>
 */
final readonly class ExportAuditLogsCsv implements Request
{
    public function __construct(
        public Id $businessId,
        public ?Id $actorId = null,
        public ?string $action = null,
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $to = null,
    ) {}
}
