<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\AuditLog\Models\AuditLog;
use App\Modules\AuditLog\UseCases\ListAuditLogs\ListAuditLogs;
use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Businesses\Models\Business;
use AvoqadoDev\UseCase\Facades\Mediator;
use Carbon\CarbonImmutable;
use Shared\ValueObjects\Id;

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->actor = User::create([
        'name' => 'Owner',
        'email' => 'o@x.com',
        'workos_id' => 'w-1',
        'avatar' => '',
        'role' => UserRole::Owner->value,
        'business_id' => $this->business->id,
    ]);
});

it('returns logs scoped to the business, newest first, as DTO entries', function () {
    AuditLog::create([
        'business_id' => $this->business->id,
        'actor_id' => $this->actor->id,
        'actor_type' => 'user',
        'action' => 'document.deleted',
        'created_at' => now()->subHours(2),
    ]);
    AuditLog::create([
        'business_id' => $this->business->id,
        'actor_id' => $this->actor->id,
        'actor_type' => 'user',
        'action' => 'document.downloaded',
        'created_at' => now()->subHour(),
    ]);

    $other = Business::factory()->create();
    AuditLog::create([
        'business_id' => $other->id,
        'actor_type' => 'system',
        'action' => 'document.deleted',
        'created_at' => now(),
    ]);

    $page = Mediator::dispatch(new ListAuditLogs(
        businessId: Id::fromString($this->business->ulid),
    ));

    expect($page->total())->toBe(2)
        ->and($page->items()[0]->action)->toBe('document.downloaded')
        ->and($page->items()[1]->action)->toBe('document.deleted')
        ->and($page->items()[0]->actorName)->toBe('Owner');
});

it('filters by actor', function () {
    $other = User::create([
        'name' => 'Other',
        'email' => 'other@x.com',
        'workos_id' => 'w-2',
        'avatar' => '',
        'role' => UserRole::Member->value,
        'business_id' => $this->business->id,
    ]);

    AuditLog::create(['business_id' => $this->business->id, 'actor_id' => $this->actor->id, 'actor_type' => 'user', 'action' => 'a']);
    AuditLog::create(['business_id' => $this->business->id, 'actor_id' => $other->id, 'actor_type' => 'user', 'action' => 'b']);

    $page = Mediator::dispatch(new ListAuditLogs(
        businessId: Id::fromString($this->business->ulid),
        actorId: Id::fromString($this->actor->ulid),
    ));

    expect($page->total())->toBe(1)
        ->and($page->items()[0]->action)->toBe('a');
});

it('filters by action', function () {
    AuditLog::create(['business_id' => $this->business->id, 'actor_type' => 'user', 'action' => 'document.deleted']);
    AuditLog::create(['business_id' => $this->business->id, 'actor_type' => 'user', 'action' => 'document.downloaded']);

    $page = Mediator::dispatch(new ListAuditLogs(
        businessId: Id::fromString($this->business->ulid),
        action: 'document.deleted',
    ));

    expect($page->total())->toBe(1);
});

it('filters by date range (inclusive)', function () {
    AuditLog::create(['business_id' => $this->business->id, 'actor_type' => 'user', 'action' => 'old', 'created_at' => '2026-04-01 10:00:00']);
    AuditLog::create(['business_id' => $this->business->id, 'actor_type' => 'user', 'action' => 'mid', 'created_at' => '2026-04-15 10:00:00']);
    AuditLog::create(['business_id' => $this->business->id, 'actor_type' => 'user', 'action' => 'new', 'created_at' => '2026-05-01 10:00:00']);

    $page = Mediator::dispatch(new ListAuditLogs(
        businessId: Id::fromString($this->business->ulid),
        from: CarbonImmutable::parse('2026-04-10'),
        to: CarbonImmutable::parse('2026-04-30'),
    ));

    expect($page->total())->toBe(1)
        ->and($page->items()[0]->action)->toBe('mid');
});
