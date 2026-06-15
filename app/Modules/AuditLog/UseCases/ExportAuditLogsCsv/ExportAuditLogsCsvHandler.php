<?php

declare(strict_types=1);

namespace App\Modules\AuditLog\UseCases\ExportAuditLogsCsv;

use App\Modules\AuditLog\Models\AuditLog;
use App\Modules\Common\QueryFilters\BelongsToBusiness;
use AvoqadoDev\UseCase\Contracts\Request;
use AvoqadoDev\UseCase\Contracts\RequestHandler;

final readonly class ExportAuditLogsCsvHandler implements RequestHandler
{
    private const HEADER = [
        'ulid',
        'created_at',
        'action',
        'actor_type',
        'actor_name',
        'actor_email',
        'target_type',
        'target_id',
        'ip',
        'user_agent',
        'meta',
    ];

    /**
     * @param  ExportAuditLogsCsv  $request
     */
    public function handle(Request $request): string
    {
        $rows = AuditLog::query()
            ->tap(new BelongsToBusiness($request->businessId))
            ->when($request->actorId, fn ($q, $actorId) => $q->where('actor_id', function ($sub) use ($actorId): void {
                $sub->select('id')->from('users')->where('ulid', $actorId->toString())->limit(1);
            }))
            ->when($request->action, fn ($q, string $action) => $q->where('action', $action))
            ->when($request->from, fn ($q, $from) => $q->where('created_at', '>=', $from))
            ->when($request->to, fn ($q, $to) => $q->where('created_at', '<=', $to))
            ->with(['actor:id,name,email'])
            ->orderByDesc('created_at')
            ->cursor();

        $buffer = fopen('php://temp', 'r+');
        fputcsv($buffer, self::HEADER);

        foreach ($rows as $log) {
            fputcsv($buffer, [
                $log->ulid,
                $log->created_at->toIso8601String(),
                $log->action,
                $log->actor_type,
                $log->actor?->name,
                $log->actor?->email,
                $log->target_type,
                $log->target_id,
                $log->ip,
                $log->user_agent,
                $log->meta !== null ? json_encode($log->meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
            ]);
        }

        rewind($buffer);
        $csv = stream_get_contents($buffer);
        fclose($buffer);

        return $csv;
    }
}
