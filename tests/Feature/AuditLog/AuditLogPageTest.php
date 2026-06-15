<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\AuditLog\Models\AuditLog;
use App\Modules\AuditLog\UseCases\ExportAuditLogsCsv\ExportAuditLogsCsv;
use App\Modules\AuditLog\UseCases\ListAuditLogs\ListAuditLogs;
use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Businesses\Models\Business;
use AvoqadoDev\UseCase\Facades\Mediator;
use Illuminate\Pagination\LengthAwarePaginator;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutMiddleware(ValidateSessionWithWorkOS::class);

    $this->business = Business::factory()->create();
    $this->owner = User::create([
        'name' => 'Owner',
        'email' => 'o@x.com',
        'workos_id' => 'w-1',
        'avatar' => '',
        'role' => UserRole::Owner->value,
        'business_id' => $this->business->id,
    ]);
    $this->member = User::create([
        'name' => 'Member',
        'email' => 'm@x.com',
        'workos_id' => 'w-2',
        'avatar' => '',
        'role' => UserRole::Member->value,
        'business_id' => $this->business->id,
    ]);
});

it('renders the page for an owner with a row per audit entry', function () {
    AuditLog::create([
        'business_id' => $this->business->id,
        'actor_id' => $this->owner->id,
        'actor_type' => 'user',
        'action' => 'document.deleted',
    ]);

    $this->actingAs($this->owner)
        ->get('/audit-logs')
        ->assertOk()
        ->assertSee('Audit log')
        ->assertSee('document.deleted');
});

it('returns 403 for a non-owner', function () {
    $this->actingAs($this->member)
        ->get('/audit-logs')
        ->assertForbidden();
});

it('redirects guests to /login', function () {
    $this->get('/audit-logs')->assertRedirect('/login');
});

it('dispatches ListAuditLogs with the actor + action filters from the form', function () {
    $this->actingAs($this->owner);

    $fake = Mediator::fake(ListAuditLogs::class, new LengthAwarePaginator([], 0, 25));

    Livewire::test('pages::audit-logs.index')
        ->set('actor', $this->owner->ulid)
        ->set('action', 'document.deleted');

    $fake->assertDispatched(
        ListAuditLogs::class,
        fn (ListAuditLogs $cmd) => $cmd->businessId->toString() === $this->business->ulid
            && $cmd->actorId?->toString() === $this->owner->ulid
            && $cmd->action === 'document.deleted'
    );
});

it('downloads a CSV for an owner via the export endpoint', function () {
    AuditLog::create([
        'business_id' => $this->business->id,
        'actor_id' => $this->owner->id,
        'actor_type' => 'user',
        'action' => 'document.deleted',
    ]);

    $response = $this->actingAs($this->owner)->get('/audit-logs/export');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');

    $content = $response->streamedContent();
    expect($content)->toContain('document.deleted');
});

it('returns 403 from the export endpoint for a non-owner', function () {
    $this->actingAs($this->member)->get('/audit-logs/export')->assertForbidden();
});

it('export endpoint dispatches with the requested filters', function () {
    $this->actingAs($this->owner);

    $fake = Mediator::fake(ExportAuditLogsCsv::class, "ulid\n");

    $this->get('/audit-logs/export?actor='.$this->owner->ulid.'&action=document.deleted&from=2026-04-01&to=2026-04-30')
        ->assertOk();

    $fake->assertDispatched(
        ExportAuditLogsCsv::class,
        fn (ExportAuditLogsCsv $cmd) => $cmd->businessId->toString() === $this->business->ulid
            && $cmd->actorId?->toString() === $this->owner->ulid
            && $cmd->action === 'document.deleted'
            && $cmd->from?->toDateString() === '2026-04-01'
            && $cmd->to?->toDateString() === '2026-04-30'
    );
});
