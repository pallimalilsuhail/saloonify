<?php

declare(strict_types=1);

namespace App\Modules\AuditLog\UseCases\RecordAuditEvent;

use App\Models\User;
use App\Modules\AuditLog\Models\AuditLog;
use App\Modules\Businesses\Models\Business;
use AvoqadoDev\UseCase\Contracts\Request;
use AvoqadoDev\UseCase\Contracts\RequestHandler;
use Shared\ValueObjects\Id;

final readonly class RecordAuditEventHandler implements RequestHandler
{
    /**
     * @param  RecordAuditEvent  $request
     */
    public function handle(Request $request): Id
    {
        $businessId = $request->businessId !== null
            ? Business::query()->where('ulid', $request->businessId->toString())->value('id')
            : null;

        $actorId = $request->actorId !== null
            ? User::query()->where('ulid', $request->actorId->toString())->value('id')
            : null;

        $log = AuditLog::create([
            'business_id' => $businessId,
            'actor_id' => $actorId,
            'actor_type' => $request->actorType,
            'action' => $request->action,
            'target_type' => $request->targetType,
            'target_id' => $request->targetId,
            'ip' => $request->ip,
            'user_agent' => $request->userAgent,
            'meta' => $request->meta,
        ]);

        return Id::fromString($log->ulid);
    }
}
