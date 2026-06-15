<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\AuditLog\Enums\ActorType;
use App\Modules\AuditLog\Models\AuditLog;
use App\Modules\AuditLog\UseCases\RecordAuditEvent\RecordAuditEvent;
use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Businesses\Models\Business;
use AvoqadoDev\UseCase\Facades\Mediator;
use Shared\ValueObjects\Id;

it('persists an audit row with resolved business + actor foreign keys', function () {
    $business = Business::factory()->create();
    $actor = User::create([
        'name' => 'Owner',
        'email' => 'o@x.com',
        'workos_id' => 'w-1',
        'avatar' => '',
        'role' => UserRole::Owner->value,
        'business_id' => $business->id,
    ]);

    $logId = Mediator::dispatch(new RecordAuditEvent(
        action: 'document.deleted',
        actorType: ActorType::User->value,
        businessId: Id::fromString($business->ulid),
        actorId: Id::fromString($actor->ulid),
        targetType: 'Document',
        targetId: '01HZZZZZZZZZZZZZZZZZZZZZZZ',
        ip: '127.0.0.1',
        userAgent: 'TestAgent/1.0',
        meta: ['note' => 'unit test'],
    ));

    $log = AuditLog::query()->where('ulid', $logId->toString())->firstOrFail();

    expect($log->business_id)->toBe($business->id)
        ->and($log->actor_id)->toBe($actor->id)
        ->and($log->actor_type)->toBe('user')
        ->and($log->action)->toBe('document.deleted')
        ->and($log->target_type)->toBe('Document')
        ->and($log->target_id)->toBe('01HZZZZZZZZZZZZZZZZZZZZZZZ')
        ->and($log->ip)->toBe('127.0.0.1')
        ->and($log->user_agent)->toBe('TestAgent/1.0')
        ->and($log->meta)->toBe(['note' => 'unit test']);
});

it('persists with null business + actor when not provided (system action)', function () {
    $logId = Mediator::dispatch(new RecordAuditEvent(
        action: 'auth.login.failed',
        actorType: ActorType::Anonymous->value,
        meta: ['email' => 'unknown@x.com'],
    ));

    $log = AuditLog::query()->where('ulid', $logId->toString())->firstOrFail();

    expect($log->business_id)->toBeNull()
        ->and($log->actor_id)->toBeNull()
        ->and($log->actor_type)->toBe('anonymous')
        ->and($log->action)->toBe('auth.login.failed')
        ->and($log->meta)->toBe(['email' => 'unknown@x.com']);
});
