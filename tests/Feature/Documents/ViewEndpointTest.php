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
    $this->member = User::create([
        'name' => 'Member',
        'email' => 'm@x.com',
        'workos_id' => 'wm-1',
        'avatar' => '',
        'role' => UserRole::Member->value,
        'business_id' => $this->business->id,
    ]);

    $mock = Mockery::mock(CloudStorageService::class);
    $mock->shouldReceive('generatePresignedDownloadUrl')
        ->andReturn('https://s3.example/view?signed=1');
    app()->instance(CloudStorageService::class, $mock);
});

it('redirects an authed business member to the signed view URL', function () {
    $response = $this->actingAs($this->member)
        ->get("/documents/{$this->document->ulid}/view");

    $response->assertRedirect('https://s3.example/view?signed=1');
});

it('redirects guests to /login', function () {
    $response = $this->get("/documents/{$this->document->ulid}/view");

    $response->assertRedirect('/login');
});

it('returns 403 when the authed user has no business', function () {
    $admin = User::create([
        'name' => 'Admin',
        'email' => 'admin@x.com',
        'workos_id' => 'wsa-1',
        'avatar' => '',
        'role' => UserRole::SuperAdmin->value,
    ]);

    $response = $this->actingAs($admin)
        ->get("/documents/{$this->document->ulid}/view");

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

    $response = $this->actingAs($this->member)
        ->get("/documents/{$otherDoc->ulid}/view");

    $response->assertNotFound();
});
