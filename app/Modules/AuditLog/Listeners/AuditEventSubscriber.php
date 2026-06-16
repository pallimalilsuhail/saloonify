<?php

declare(strict_types=1);

namespace App\Modules\AuditLog\Listeners;

use App\Models\User;
use App\Modules\AuditLog\Enums\ActorType;
use App\Modules\AuditLog\Support\RequestContext;
use App\Modules\AuditLog\UseCases\RecordAuditEvent\RecordAuditEvent;
use AvoqadoDev\UseCase\Contracts\Mediator;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Events\Dispatcher;
use Shared\ValueObjects\Id;
use Throwable;

/**
 * Maps sensitive domain events onto an AuditLog row via RecordAuditEvent.
 * Listeners never throw — failure to log must not break the originating
 * action; the catch is intentional and silent (the logger captures it).
 *
 * Domain-specific subscriptions (sales, staff, catalog, …) are added as
 * those modules emit their events.
 */
final class AuditEventSubscriber
{
    public function __construct(
        private readonly Mediator $mediator,
    ) {}

    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'onLogin',
            Failed::class => 'onLoginFailed',
        ];
    }

    public function onLogin(Login $event): void
    {
        $user = $event->user;
        $actorId = $user instanceof User ? Id::fromString($user->ulid) : null;
        $businessUlid = $user instanceof User && $user->business !== null ? $user->business->ulid : null;

        $this->record(
            action: 'auth.login.success',
            actorId: $actorId,
            businessId: $businessUlid !== null ? Id::fromString($businessUlid) : null,
            targetType: 'User',
            targetId: $actorId?->toString(),
            meta: ['guard' => $event->guard],
        );
    }

    public function onLoginFailed(Failed $event): void
    {
        $user = $event->user;
        $actorId = $user instanceof User ? Id::fromString($user->ulid) : null;
        $businessUlid = $user instanceof User && $user->business !== null ? $user->business->ulid : null;

        $email = is_array($event->credentials) ? ($event->credentials['email'] ?? null) : null;

        $this->record(
            action: 'auth.login.failed',
            actorType: $actorId !== null ? ActorType::User : ActorType::Anonymous,
            actorId: $actorId,
            businessId: $businessUlid !== null ? Id::fromString($businessUlid) : null,
            meta: ['email' => $email, 'guard' => $event->guard],
        );
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    private function record(
        string $action,
        ?Id $actorId = null,
        ?Id $businessId = null,
        ?string $targetType = null,
        ?string $targetId = null,
        ?array $meta = null,
        ?ActorType $actorType = null,
    ): void {
        try {
            $context = RequestContext::pull();

            $resolvedActorType = $actorType
                ?? ($actorId !== null ? ActorType::User : ActorType::System);

            $this->mediator->dispatch(new RecordAuditEvent(
                action: $action,
                actorType: $resolvedActorType->value,
                businessId: $businessId,
                actorId: $actorId,
                targetType: $targetType,
                targetId: $targetId,
                ip: $context['ip'],
                userAgent: $context['userAgent'],
                meta: $meta,
            ));
        } catch (Throwable) {
            // Audit logging must never break the originating action.
        }
    }
}
