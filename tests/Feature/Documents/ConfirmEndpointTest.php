<?php

declare(strict_types=1);

use App\Modules\Businesses\Models\Business;
use App\Modules\Customers\Models\Customer;
use App\Modules\DocumentRequests\Enums\UploadSessionStatus;
use App\Modules\DocumentRequests\Models\UploadSession;
use App\Modules\Documents\Models\Document;
use App\Services\CloudStorage\CloudStorageService;
use Shared\ValueObjects\Id;
use Shared\ValueObjects\Token;

function bindStorage(callable $existsResolver): void
{
    $mock = Mockery::mock(CloudStorageService::class);
    $mock->shouldReceive('objectExists')->andReturnUsing($existsResolver);
    app()->instance(CloudStorageService::class, $mock);
}

function endpointSession(Token $token, array $overrides = []): UploadSession
{
    $business = Business::factory()->create();
    $customer = Customer::factory()->for($business)->create();

    return UploadSession::factory()->create(array_merge([
        'business_id' => $business->id,
        'customer_id' => $customer->id,
        'status' => UploadSessionStatus::Active->value,
        'expires_at' => now()->addHour(),
        'token_hash' => $token->hash(),
    ], $overrides));
}

it('returns 200 + submitted=true when every document is verified', function () {
    bindStorage(fn () => true);

    $token = Token::generate();
    $session = endpointSession($token);
    $doc = Document::factory()->create([
        'business_id' => $session->business_id,
        'customer_id' => $session->customer_id,
        'upload_session_id' => $session->id,
    ]);

    $response = $this->postJson("/api/u/{$token->urlSafe()}/confirm", [
        'document_ids' => [$doc->ulid],
    ]);

    $response->assertOk()
        ->assertJsonPath('submitted', true)
        ->assertJsonPath('confirmed.0', $doc->ulid);
});

it('returns 207 + submitted=false + missing list on partial verification', function () {
    $token = Token::generate();
    $session = endpointSession($token);
    $present = Document::factory()->create([
        'business_id' => $session->business_id,
        'customer_id' => $session->customer_id,
        'upload_session_id' => $session->id,
    ]);
    $absent = Document::factory()->create([
        'business_id' => $session->business_id,
        'customer_id' => $session->customer_id,
        'upload_session_id' => $session->id,
    ]);

    bindStorage(fn (string $key) => $key === $present->s3_key);

    $response = $this->postJson("/api/u/{$token->urlSafe()}/confirm", [
        'document_ids' => [$present->ulid, $absent->ulid],
    ]);

    $response->assertStatus(207)
        ->assertJsonPath('submitted', false)
        ->assertJsonPath('missing.0', $absent->ulid);
});

it('returns 410 when the session is no longer active', function () {
    bindStorage(fn () => true);

    $token = Token::generate();
    endpointSession($token, ['expires_at' => now()->subMinute()]);

    $response = $this->postJson("/api/u/{$token->urlSafe()}/confirm", [
        'document_ids' => [Id::generate()->toString()],
    ]);

    $response->assertStatus(410);
});

it('returns 404 when the token is malformed', function () {
    $response = $this->postJson('/api/u/not-a-token/confirm', [
        'document_ids' => [Id::generate()->toString()],
    ]);

    $response->assertNotFound();
});

it('returns 422 when document_ids is missing', function () {
    bindStorage(fn () => true);

    $token = Token::generate();
    endpointSession($token);

    $response = $this->postJson("/api/u/{$token->urlSafe()}/confirm", []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['document_ids']);
});

it('returns 422 when a document_id is not a valid ULID', function () {
    bindStorage(fn () => true);

    $token = Token::generate();
    endpointSession($token);

    $response = $this->postJson("/api/u/{$token->urlSafe()}/confirm", [
        'document_ids' => ['not-a-ulid'],
    ]);

    $response->assertStatus(422);
});

it('does not require CSRF (stateless)', function () {
    bindStorage(fn () => true);

    $token = Token::generate();
    $session = endpointSession($token);
    $doc = Document::factory()->create([
        'business_id' => $session->business_id,
        'customer_id' => $session->customer_id,
        'upload_session_id' => $session->id,
    ]);

    $response = $this->postJson("/api/u/{$token->urlSafe()}/confirm", [
        'document_ids' => [$doc->ulid],
    ]);

    $response->assertOk();
});
