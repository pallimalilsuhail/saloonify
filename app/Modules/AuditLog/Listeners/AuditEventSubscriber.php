<?php

declare(strict_types=1);

namespace App\Modules\AuditLog\Listeners;

use App\Models\User;
use App\Modules\AuditLog\Enums\ActorType;
use App\Modules\AuditLog\Support\RequestContext;
use App\Modules\AuditLog\UseCases\RecordAuditEvent\RecordAuditEvent;
use App\Modules\Businesses\Events\InvitationConsumed;
use App\Modules\Businesses\Events\InvitationIssued;
use App\Modules\Businesses\Events\InvitationRevoked;
use App\Modules\Businesses\Events\MemberRemoved;
use App\Modules\Businesses\Events\MemberRoleChanged;
use AvoqadoDev\UseCase\Contracts\Mediator;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Events\Dispatcher;
use Shared\ValueObjects\Id;
use Throwable;

/**
 * Maps every sensitive domain event onto an AuditLog row via
 * RecordAuditEvent. Listeners never throw — failure to log must not
 * break the originating action. The catch is intentional and silent;
 * the underlying logger captures the exception via Sentry / file logs.
 */
final class AuditEventSubscriber
{
    public function __construct(
        private readonly Mediator $mediator,
    ) {}

    public function subscribe(Dispatcher $events): array
    {
        return [
            InvitationIssued::class => 'onInvitationIssued',
            InvitationConsumed::class => 'onInvitationConsumed',
            InvitationRevoked::class => 'onInvitationRevoked',
            MemberRoleChanged::class => 'onMemberRoleChanged',
            MemberRemoved::class => 'onMemberRemoved',
            Login::class => 'onLogin',
            Failed::class => 'onLoginFailed',
        ];
    }

    public function onInvitationIssued(InvitationIssued $event): void
    {
        $this->record(
            action: 'invitation.issued',
            actorId: $event->invitedById,
            businessId: $event->businessId,
            targetType: 'Invitation',
            targetId: $event->invitationId->toString(),
            meta: ['email' => $event->email, 'role' => $event->role->value],
        );
    }

    public function onInvitationConsumed(InvitationConsumed $event): void
    {
        $this->record(
            action: 'invitation.consumed',
            actorId: $event->userId,
            businessId: $event->businessId,
            targetType: 'Invitation',
            targetId: $event->invitationId->toString(),
        );
    }

    public function onInvitationRevoked(InvitationRevoked $event): void
    {
        $this->record(
            action: 'invitation.revoked',
            actorId: $event->revokedById,
            businessId: $event->businessId,
            targetType: 'Invitation',
            targetId: $event->invitationId->toString(),
        );
    }

    public function onMemberRoleChanged(MemberRoleChanged $event): void
    {
        $this->record(
            action: 'member.role_changed',
            actorId: $event->changedById,
            businessId: $event->businessId,
            targetType: 'User',
            targetId: $event->memberId->toString(),
            meta: ['from' => $event->fromRole->value, 'to' => $event->toRole->value],
        );
    }

    public function onMemberRemoved(MemberRemoved $event): void
    {
        $this->record(
            action: 'member.removed',
            actorId: $event->removedById,
            businessId: $event->businessId,
            targetType: 'User',
            targetId: $event->memberId->toString(),
        );
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
