<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\AuditLog\Models\AuditLog;
use App\Modules\AuditLog\UseCases\ExportAuditLogsCsv\ExportAuditLogsCsv;
use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Businesses\Models\Business;
use AvoqadoDev\UseCase\Facades\Mediator;
use Shared\ValueObjects\Id;

it('renders a CSV with the expected header + a row per matching log scoped to the business', function () {
    $business = Business::factory()->create();
    $other = Business::factory()->create();
    $actor = User::create([
        'name' => 'Owner',
        'email' => 'o@x.com',
        'workos_id' => 'w-1',
        'avatar' => '',
        'role' => UserRole::Owner->value,
        'business_id' => $business->id,
    ]);

    AuditLog::create([
        'business_id' => $business->id,
        'actor_id' => $actor->id,
        'actor_type' => 'user',
        'action' => 'document.deleted',
        'target_type' => 'Document',
        'target_id' => '01ABCDEFGHJKMNPQRSTVWXYZ00',
        'ip' => '127.0.0.1',
        'user_agent' => 'TestAgent',
        'meta' => ['note' => 'first'],
    ]);
    AuditLog::create([
        'business_id' => $other->id,
        'actor_type' => 'system',
        'action' => 'document.deleted',
    ]);

    $csv = Mediator::dispatch(new ExportAuditLogsCsv(
        businessId: Id::fromString($business->ulid),
    ));

    $lines = array_values(array_filter(preg_split("/\r\n|\n/", $csv)));

    expect($lines)->toHaveCount(2)
        ->and($lines[0])->toContain('ulid,created_at,action,actor_type,actor_name,actor_email,target_type,target_id,ip,user_agent,meta')
        ->and($lines[1])->toContain('document.deleted')
        ->and($lines[1])->toContain('Owner')
        ->and($lines[1])->toContain('o@x.com')
        ->and($lines[1])->toContain('127.0.0.1')
        ->and($lines[1])->toContain('"{""note"":""first""}"');
});
