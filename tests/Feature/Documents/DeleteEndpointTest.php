<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Businesses\Models\Business;
use App\Modules\Customers\Models\Customer;
use App\Modules\DocumentRequests\Models\UploadSession;
use App\Modules\Documents\Models\Document;
use App\Services\CloudStorage\CloudStorageService;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;

beforeEach(function () {
    $this->withoutMiddleware(ValidateSessionWithWorkOS::class);

    $this->business = Business::factory()->create();
    $this->customer = Customer::factory()->for($this->business)->create();
    $this->session = UploadSession::factory()->create([
        'business_id' => $this->business->id,
        'customer_id' => $this->customer->id,
    ]);
    $this->document = Document::factory()->create([
        'business_id' => $this->business->id,
        'customer_id' => $this->customer->id,
        'upload_session_id' => $this->session->id,
    ]);
    $this->owner = User::create([
        'name' => 'Owner',
        'email' => 'o@x.com',
        'workos_id' => 'wo-1',
        'avatar' => '',
        'role' => UserRole::Owner->value,
        'business_id' => $this->business->id,
    ]);
    $this->member = User::create([
        'name' => 'Member',
        'email' => 'm@x.com',
        'workos_id' => 'wm-1',
        'avatar' => '',
        'role' => UserRole::Member->value,
        'business_id' => $this->business->id,
    ]);

    $mock = Mockery::mock(CloudStorageService::class);
    $mock->shouldReceive('tagObject');
    app()->instance(CloudStorageService::class, $mock);
});

it('soft-deletes for an authed owner and redirects back', function () {
    $response = $this->actingAs($this->owner)
        ->from('/customers/'.$this->customer->ulid)
        ->delete("/documents/{$this->document->ulid}");

    $response->assertRedirect('/customers/'.$this->customer->ulid);
    expect(Document::find($this->document->id))->toBeNull();
});

it('returns 403 for a member (owner-only)', function () {
    $response = $this->actingAs($this->member)
        ->delete("/documents/{$this->document->ulid}");

    $response->assertForbidden();
    expect(Document::find($this->document->id))->not->toBeNull();
});

it('redirects guests to /login', function () {
    $response = $this->delete("/documents/{$this->document->ulid}");

    $response->assertRedirect('/login');
});

it('returns 403 for an authed user without a business (super_admin direct hit)', function () {
    $admin = User::create([
        'name' => 'Admin',
        'email' => 'admin@x.com',
        'workos_id' => 'wsa-1',
        'avatar' => '',
        'role' => UserRole::SuperAdmin->value,
    ]);

    $response = $this->actingAs($admin)
        ->delete("/documents/{$this->document->ulid}");

    $response->assertForbidden();
});

it('returns 404 when the document is in a different business', function () {
    $otherBusiness = Business::factory()->create();
    $otherCustomer = Customer::factory()->for($otherBusiness)->create();
    $otherSession = UploadSession::factory()->create([
        'business_id' => $otherBusiness->id,
        'customer_id' => $otherCustomer->id,
    ]);
    $otherDoc = Document::factory()->create([
        'business_id' => $otherBusiness->id,
        'customer_id' => $otherCustomer->id,
        'upload_session_id' => $otherSession->id,
    ]);

    $response = $this->actingAs($this->owner)
        ->delete("/documents/{$otherDoc->ulid}");

    $response->assertNotFound();
});
