<?php

declare(strict_types=1);

use App\Modules\Businesses\Models\Business;
use App\Modules\Customers\Models\Customer;
use App\Modules\DocumentRequests\Enums\UploadSessionStatus;
use App\Modules\DocumentRequests\Models\UploadSession;
use App\Modules\Documents\DTOs\PresignedDocumentUpload;
use App\Modules\Documents\Enums\DocumentStatus;
use App\Modules\Documents\Exceptions\UploadSessionNotAccepting;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\UseCases\PresignDocumentUpload\PresignDocumentUpload;
use App\Services\CloudStorage\CloudStorageService;
use App\Services\CloudStorage\PresignedUploadResult;
use AvoqadoDev\UseCase\BusinessRules\Exceptions\BusinessRuleException;
use AvoqadoDev\UseCase\Facades\Mediator;
use Mockery\MockInterface;
use Shared\ValueObjects\Token;

function fakeCloudStorage(?PresignedUploadResult $result = null): CloudStorageService
{
    $mock = Mockery::mock(CloudStorageService::class);
    $mock->shouldReceive('generatePresignedUploadUrl')
        ->andReturn($result ?? new PresignedUploadResult(
            uploadUrl: 'https://s3.example/bucket/key?signed=1',
            filePath: 'business/biz/customer/cust/session/sess/abc-doc.pdf',
            expiresAt: now()->addMinutes(5)->toIso8601String(),
        ));

    return $mock;
}

function makeActiveSession(array $overrides = []): UploadSession
{
    $business = Business::factory()->create();
    $customer = Customer::factory()->for($business)->create();

    return UploadSession::factory()->create(array_merge([
        'business_id' => $business->id,
        'customer_id' => $customer->id,
        'status' => UploadSessionStatus::Active->value,
        'expires_at' => now()->addHour(),
        'allowed_mime' => ['application/pdf', 'image/png'],
        'max_bytes' => 25 * 1024 * 1024,
        'max_files' => 20,
    ], $overrides));
}

beforeEach(function () {
    app()->instance(CloudStorageService::class, fakeCloudStorage());
});

it('creates a pending Document row and returns a presigned upload DTO', function () {
    $token = Token::generate();
    makeActiveSession(['token_hash' => $token->hash()]);

    $result = Mediator::dispatch(new PresignDocumentUpload(
        token: $token,
        filename: 'passport.pdf',
        mime: 'application/pdf',
        sizeBytes: 1024,
    ));

    expect($result)->toBeInstanceOf(PresignedDocumentUpload::class)
        ->and($result->uploadUrl)->toContain('signed=1');

    $document = Document::query()->where('ulid', $result->documentId->toString())->firstOrFail();

    expect($document->status)->toBe(DocumentStatus::Pending)
        ->and($document->original_name)->toBe('passport.pdf')
        ->and($document->mime)->toBe('application/pdf')
        ->and($document->size_bytes)->toBe(1024)
        ->and($document->s3_key)->toBe('business/biz/customer/cust/session/sess/abc-doc.pdf');
});

it('throws UploadSessionNotAccepting when the token does not match', function () {
    Mediator::dispatch(new PresignDocumentUpload(
        token: Token::generate(),
        filename: 'x.pdf',
        mime: 'application/pdf',
        sizeBytes: 1024,
    ));
})->throws(UploadSessionNotAccepting::class);

it('throws UploadSessionNotAccepting when the session is expired', function () {
    $token = Token::generate();
    makeActiveSession([
        'token_hash' => $token->hash(),
        'expires_at' => now()->subMinute(),
    ]);

    Mediator::dispatch(new PresignDocumentUpload(
        token: $token,
        filename: 'x.pdf',
        mime: 'application/pdf',
        sizeBytes: 1024,
    ));
})->throws(UploadSessionNotAccepting::class);

it('throws UploadSessionNotAccepting when the session is submitted', function () {
    $token = Token::generate();
    makeActiveSession([
        'token_hash' => $token->hash(),
        'status' => UploadSessionStatus::Submitted->value,
    ]);

    Mediator::dispatch(new PresignDocumentUpload(
        token: $token,
        filename: 'x.pdf',
        mime: 'application/pdf',
        sizeBytes: 1024,
    ));
})->throws(UploadSessionNotAccepting::class);

it('rejects a disallowed mime via guard rule', function () {
    $token = Token::generate();
    makeActiveSession([
        'token_hash' => $token->hash(),
        'allowed_mime' => ['application/pdf'],
    ]);

    Mediator::dispatch(new PresignDocumentUpload(
        token: $token,
        filename: 'evil.exe',
        mime: 'application/x-msdownload',
        sizeBytes: 1024,
    ));
})->throws(BusinessRuleException::class, 'not allowed');

it('rejects a file that exceeds the per-file size cap', function () {
    $token = Token::generate();
    makeActiveSession([
        'token_hash' => $token->hash(),
        'max_bytes' => 1024,
    ]);

    Mediator::dispatch(new PresignDocumentUpload(
        token: $token,
        filename: 'big.pdf',
        mime: 'application/pdf',
        sizeBytes: 2048,
    ));
})->throws(BusinessRuleException::class, 'exceeds');

it('rejects when the session has already reached its file count', function () {
    $token = Token::generate();
    $session = makeActiveSession([
        'token_hash' => $token->hash(),
        'max_files' => 2,
    ]);

    Document::factory()->count(2)->create([
        'business_id' => $session->business_id,
        'customer_id' => $session->customer_id,
        'upload_session_id' => $session->id,
    ]);

    Mediator::dispatch(new PresignDocumentUpload(
        token: $token,
        filename: 'extra.pdf',
        mime: 'application/pdf',
        sizeBytes: 1024,
    ));
})->throws(BusinessRuleException::class, 'limit');

it('passes the correct folder structure to the cloud storage service', function () {
    $token = Token::generate();
    $session = makeActiveSession(['token_hash' => $token->hash()]);

    $captured = null;
    /** @var MockInterface $mock */
    $mock = Mockery::mock(CloudStorageService::class);
    $mock->shouldReceive('generatePresignedUploadUrl')
        ->withArgs(function (string $folder, string $filename, string $mime, int $size) use (&$captured): bool {
            $captured = $folder;

            return true;
        })
        ->andReturn(new PresignedUploadResult(
            uploadUrl: 'https://s3.example/x',
            filePath: $session->business->ulid.'/'.$session->customer->ulid,
            expiresAt: now()->addMinutes(5)->toIso8601String(),
        ));
    app()->instance(CloudStorageService::class, $mock);

    Mediator::dispatch(new PresignDocumentUpload(
        token: $token,
        filename: 'doc.pdf',
        mime: 'application/pdf',
        sizeBytes: 1024,
    ));

    expect($captured)->toBe(sprintf(
        'business/%s/customer/%s/session/%s',
        $session->business->ulid,
        $session->customer->ulid,
        $session->ulid,
    ));
});
