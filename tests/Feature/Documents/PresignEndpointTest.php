<?php

declare(strict_types=1);

use App\Modules\Businesses\Models\Business;
use App\Modules\Customers\Models\Customer;
use App\Modules\DocumentRequests\Enums\UploadSessionStatus;
use App\Modules\DocumentRequests\Models\UploadSession;
use App\Modules\Documents\Models\Document;
use App\Services\CloudStorage\CloudStorageService;
use App\Services\CloudStorage\PresignedUploadResult;
use Shared\ValueObjects\Token;

beforeEach(function () {
    $mock = Mockery::mock(CloudStorageService::class);
    $mock->shouldReceive('generatePresignedUploadUrl')
        ->andReturn(new PresignedUploadResult(
            uploadUrl: 'https://s3.example/bucket/key?signed=1',
            filePath: 'business/biz/customer/cust/session/sess/abc-doc.pdf',
            expiresAt: now()->addMinutes(5)->toIso8601String(),
        ));
    app()->instance(CloudStorageService::class, $mock);
});

function activeSessionWithToken(Token $token, array $overrides = []): UploadSession
{
    $business = Business::factory()->create();
    $customer = Customer::factory()->for($business)->create();

    return UploadSession::factory()->create(array_merge([
        'business_id' => $business->id,
        'customer_id' => $customer->id,
        'status' => UploadSessionStatus::Active->value,
        'expires_at' => now()->addHour(),
        'allowed_mime' => ['application/pdf'],
        'max_bytes' => 25 * 1024 * 1024,
        'max_files' => 20,
        'token_hash' => $token->hash(),
    ], $overrides));
}

it('returns 201 + presigned payload + creates a pending Document on success', function () {
    $token = Token::generate();
    activeSessionWithToken($token);

    $response = $this->postJson("/api/u/{$token->urlSafe()}/presign", [
        'filename' => 'passport.pdf',
        'mime' => 'application/pdf',
        'size' => 1024,
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['document_id', 'upload_url', 's3_key', 'expires_at', 'method']);

    expect(Document::count())->toBe(1);
});

it('returns 404 when the token is malformed', function () {
    $response = $this->postJson('/api/u/not-a-real-token/presign', [
        'filename' => 'x.pdf',
        'mime' => 'application/pdf',
        'size' => 1,
    ]);

    $response->assertNotFound();
});

it('returns 410 when the session is expired', function () {
    $token = Token::generate();
    activeSessionWithToken($token, ['expires_at' => now()->subMinute()]);

    $response = $this->postJson("/api/u/{$token->urlSafe()}/presign", [
        'filename' => 'x.pdf',
        'mime' => 'application/pdf',
        'size' => 1,
    ]);

    $response->assertStatus(410);
});

it('returns 422 when the mime is not allowed', function () {
    $token = Token::generate();
    activeSessionWithToken($token, ['allowed_mime' => ['application/pdf']]);

    $response = $this->postJson("/api/u/{$token->urlSafe()}/presign", [
        'filename' => 'evil.exe',
        'mime' => 'application/x-msdownload',
        'size' => 1024,
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('code', 'document.mime.not_allowed');
});

it('returns 422 when the file size exceeds the limit', function () {
    $token = Token::generate();
    activeSessionWithToken($token, ['max_bytes' => 1024]);

    $response = $this->postJson("/api/u/{$token->urlSafe()}/presign", [
        'filename' => 'big.pdf',
        'mime' => 'application/pdf',
        'size' => 2048,
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('code', 'document.size.exceeded');
});

it('returns 422 when the session is at file count', function () {
    $token = Token::generate();
    $session = activeSessionWithToken($token, ['max_files' => 1]);
    Document::factory()->create([
        'business_id' => $session->business_id,
        'customer_id' => $session->customer_id,
        'upload_session_id' => $session->id,
    ]);

    $response = $this->postJson("/api/u/{$token->urlSafe()}/presign", [
        'filename' => 'second.pdf',
        'mime' => 'application/pdf',
        'size' => 1024,
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('code', 'document.count.exceeded');
});

it('returns 422 when required fields are missing', function () {
    $token = Token::generate();
    activeSessionWithToken($token);

    $response = $this->postJson("/api/u/{$token->urlSafe()}/presign", []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['filename', 'mime', 'size']);
});

it('does not require CSRF token (stateless)', function () {
    $token = Token::generate();
    activeSessionWithToken($token);

    // No X-CSRF-Token header set; should still succeed.
    $response = $this->postJson("/api/u/{$token->urlSafe()}/presign", [
        'filename' => 'doc.pdf',
        'mime' => 'application/pdf',
        'size' => 1024,
    ]);

    $response->assertCreated();
});
