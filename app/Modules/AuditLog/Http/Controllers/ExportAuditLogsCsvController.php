<?php

declare(strict_types=1);

namespace App\Modules\AuditLog\Http\Controllers;

use App\Modules\AuditLog\Http\Requests\ExportAuditLogsRequest;
use App\Modules\AuditLog\UseCases\ExportAuditLogsCsv\ExportAuditLogsCsv;
use AvoqadoDev\UseCase\Facades\Mediator;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportAuditLogsCsvController
{
    public function __invoke(ExportAuditLogsRequest $request): StreamedResponse
    {
        $csv = Mediator::dispatch(new ExportAuditLogsCsv(
            businessId: $request->getBusinessId(),
            actorId: $request->getActorId(),
            action: $request->getAction(),
            from: $request->getFrom(),
            to: $request->getTo(),
        ));

        $filename = 'audit-logs-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($csv): void {
            echo $csv;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
