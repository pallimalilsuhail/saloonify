<?php

declare(strict_types=1);

use App\Modules\Businesses\Models\Business;
use App\Modules\Customers\Models\Customer;
use App\Modules\DocumentRequests\Enums\UploadSessionStatus;
use App\Modules\DocumentRequests\Events\UploadSessionSubmitted;
use App\Modules\DocumentRequests\Models\UploadSession;
use App\Modules\Documents\DTOs\ConfirmedUpload;
use App\Modules\Documents\Enums\DocumentStatus;
use App\Modules\Documents\Exceptions\UploadSessionNotAccepting;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\UseCases\ConfirmDocumentUpload\ConfirmDocumentUpload;
use App\Services\CloudStorage\CloudStorageService;
use AvoqadoDev\UseCase\Facades\Mediator;
use Illuminate\Support\Facades\Event;
use Shared\ValueObjects\Id;
use Shared\ValueObjects\Token;

function fakeStorage(callable $existsResolver): CloudStorageService
{
    $mock = Mockery::mock(CloudStorageService::class);
    $mock->shouldReceive('objectExists')
        ->andReturnUsing($existsResolver);

    return $mock;
}

function activeSession(Token $token, array $overrides = []): UploadSession
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

it('confirms all documents, locks the session, dispatches UploadSessionSubmitted', function () {
    Event::fake([UploadSessionSubmitted::class]);
    app()->instance(CloudStorageService::class, fakeStorage(fn () => true));

    $token = Token::generate();
    $session = activeSession($token);

    $docs = Document::factory()->count(2)->create([
        'business_id' => $session->business_id,
        'customer_id' => $session->customer_id,
        'upload_session_id' => $session->id,
    ]);

    $result = Mediator::dispatch(new ConfirmDocumentUpload(
        token: $token,
        documentIds: $docs->map(fn ($d) => Id::fromString($d->ulid))->all(),
    ));

    expect($result)->toBeInstanceOf(ConfirmedUpload::class)
        ->and($result->submitted)->toBeTrue()
        ->and($result->confirmed)->toHaveCount(2)
        ->and($result->missing)->toBe([]);

    foreach ($docs as $d) {
        expect($d->fresh()->status)->toBe(DocumentStatus::Confirmed)
            ->and($d->fresh()->uploaded_at)->not->toBeNull();
    }

    expect($session->fresh()->status)->toBe(UploadSessionStatus::Submitted)
        ->and($session->fresh()->submitted_at)->not->toBeNull();

    Event::assertDispatched(
        UploadSessionSubmitted::class,
        fn (UploadSessionSubmitted $e) => $e->sessionId->toString() === $session->ulid
            && count($e->documentIds) === 2
    );
});

it('returns submitted=false and lists missing when an S3 object is absent', function () {
    Event::fake([UploadSessionSubmitted::class]);
    $token = Token::generate();
    $session = activeSession($token);

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

    app()->instance(CloudStorageService::class, fakeStorage(
        fn (string $key) => $key === $present->s3_key,
    ));

    $result = Mediator::dispatch(new ConfirmDocumentUpload(
        token: $token,
        documentIds: [Id::fromString($present->ulid), Id::fromString($absent->ulid)],
    ));

    expect($result->submitted)->toBeFalse()
        ->and($result->confirmed)->toHaveCount(1)
        ->and($result->missing)->toHaveCount(1)
        ->and($result->missing[0]->toString())->toBe($absent->ulid);

    expect($present->fresh()->status)->toBe(DocumentStatus::Confirmed)
        ->and($absent->fresh()->status)->toBe(DocumentStatus::Pending);

    expect($session->fresh()->status)->toBe(UploadSessionStatus::Active);
    Event::assertNotDispatched(UploadSessionSubmitted::class);
});

it('treats unknown document_ids as missing without confirming the session', function () {
    app()->instance(CloudStorageService::class, fakeStorage(fn () => true));

    $token = Token::generate();
    activeSession($token);

    $result = Mediator::dispatch(new ConfirmDocumentUpload(
        token: $token,
        documentIds: [Id::generate()],
    ));

    expect($result->submitted)->toBeFalse()
        ->and($result->confirmed)->toBe([])
        ->and($result->missing)->toHaveCount(1);
});

it('ignores documents that belong to a different session (security)', function () {
    app()->instance(CloudStorageService::class, fakeStorage(fn () => true));

    $token = Token::generate();
    $session = activeSession($token);

    $otherSession = UploadSession::factory()->create([
        'business_id' => $session->business_id,
        'customer_id' => $session->customer_id,
    ]);
    $foreignDoc = Document::factory()->create([
        'business_id' => $session->business_id,
        'customer_id' => $session->customer_id,
        'upload_session_id' => $otherSession->id,
    ]);

    $result = Mediator::dispatch(new ConfirmDocumentUpload(
        token: $token,
        documentIds: [Id::fromString($foreignDoc->ulid)],
    ));

    expect($result->submitted)->toBeFalse()
        ->and($result->missing)->toHaveCount(1)
        ->and($foreignDoc->fresh()->status)->toBe(DocumentStatus::Pending);
});

it('throws UploadSessionNotAccepting when the session is expired', function () {
    app()->instance(CloudStorageService::class, fakeStorage(fn () => true));
    $token = Token::generate();
    activeSession($token, ['expires_at' => now()->subMinute()]);

    Mediator::dispatch(new ConfirmDocumentUpload(
        token: $token,
        documentIds: [Id::generate()],
    ));
})->throws(UploadSessionNotAccepting::class);

it('throws UploadSessionNotAccepting when the session is already submitted', function () {
    app()->instance(CloudStorageService::class, fakeStorage(fn () => true));
    $token = Token::generate();
    activeSession($token, [
        'status' => UploadSessionStatus::Submitted->value,
        'submitted_at' => now()->subMinute(),
    ]);

    Mediator::dispatch(new ConfirmDocumentUpload(
        token: $token,
        documentIds: [Id::generate()],
    ));
})->throws(UploadSessionNotAccepting::class);

it('does not double-confirm an already-Confirmed document', function () {
    app()->instance(CloudStorageService::class, fakeStorage(function () {
        throw new RuntimeException('objectExists should not be called for already-Confirmed documents.');
    }));

    $token = Token::generate();
    $session = activeSession($token);

    $doc = Document::factory()->create([
        'business_id' => $session->business_id,
        'customer_id' => $session->customer_id,
        'upload_session_id' => $session->id,
        'status' => DocumentStatus::Confirmed->value,
        'uploaded_at' => now()->subMinute(),
    ]);
    $originalUploadedAt = $doc->uploaded_at;

    $result = Mediator::dispatch(new ConfirmDocumentUpload(
        token: $token,
        documentIds: [Id::fromString($doc->ulid)],
    ));

    expect($result->submitted)->toBeTrue()
        ->and($doc->fresh()->uploaded_at->toIso8601String())->toBe($originalUploadedAt->toIso8601String());
});
