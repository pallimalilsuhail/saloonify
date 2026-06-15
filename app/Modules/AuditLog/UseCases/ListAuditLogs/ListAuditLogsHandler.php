<?php

declare(strict_types=1);

namespace App\Modules\AuditLog\UseCases\ListAuditLogs;

use App\Modules\AuditLog\DTOs\AuditLogEntry;
use App\Modules\AuditLog\Models\AuditLog;
use App\Modules\Common\QueryFilters\BelongsToBusiness;
use AvoqadoDev\UseCase\Contracts\Request;
use AvoqadoDev\UseCase\Contracts\RequestHandler;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class ListAuditLogsHandler implements RequestHandler
{
    /**
     * @param  ListAuditLogs  $request
     */
    public function handle(Request $request): LengthAwarePaginator
    {
        return AuditLog::query()
            ->tap(new BelongsToBusiness($request->businessId))
            ->when($request->actorId, fn ($q, $actorId) => $q->where('actor_id', function ($sub) use ($actorId): void {
                $sub->select('id')->from('users')->where('ulid', $actorId->toString())->limit(1);
            }))
            ->when($request->action, fn ($q, string $action) => $q->where('action', $action))
            ->when($request->from, fn ($q, $from) => $q->where('created_at', '>=', $from))
            ->when($request->to, fn ($q, $to) => $q->where('created_at', '<=', $to))
            ->with(['actor:id,ulid,name,email'])
            ->orderByDesc('created_at')
            ->paginate($request->perPage)
            ->through(fn (AuditLog $log) => AuditLogEntry::fromModel($log));
    }
}
