<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Businesses\Models\Business;
use App\Modules\Customers\Models\Customer;
use App\Modules\DocumentRequests\Models\UploadSession;
use App\Modules\Documents\DTOs\DocumentViewUrl;
use App\Modules\Documents\Events\DocumentViewUrlIssued;
use App\Modules\Documents\Exceptions\DocumentAccessDenied;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\UseCases\GenerateDocumentViewUrl\GenerateDocumentViewUrl;
use App\Services\CloudStorage\CloudStorageService;
use AvoqadoDev\UseCase\Facades\Mediator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Event;
use Shared\ValueObjects\Id;

beforeEach(function () {
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
        's3_key' => 'business/biz/customer/cust/session/sess/doc.pdf',
    ]);
    $this->member = User::create([
        'name' => 'Member',
        'email' => 'm@x.com',
        'workos_id' => 'wm-1',
        'avatar' => '',
        'role' => UserRole::Member->value,
        'business_id' => $this->business->id,
    ]);
});

function bindMockStorage(string $signedUrl): void
{
    $mock = Mockery::mock(CloudStorageService::class);
    $mock->shouldReceive('generatePresignedDownloadUrl')
        ->andReturn($signedUrl);
    app()->instance(CloudStorageService::class, $mock);
}

it('returns a DocumentViewUrl DTO with the signed URL and 60-minute expiry by default', function () {
    bindMockStorage('https://s3.example/bucket/key?signed=1');

    $result = Mediator::dispatch(app(GenerateDocumentViewUrl::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'documentId' => Id::fromString($this->document->ulid),
        'actorId' => Id::fromString($this->member->ulid),
    ]));

    expect($result)->toBeInstanceOf(DocumentViewUrl::class)
        ->and($result->url)->toBe('https://s3.example/bucket/key?signed=1')
        ->and($result->documentId->toString())->toBe($this->document->ulid)
        ->and($result->expiresAt->diffInMinutes(now()->addMinutes(60)))->toBeLessThan(1);
});

it('passes the requested expiry to the cloud storage service', function () {
    $captured = null;
    $mock = Mockery::mock(CloudStorageService::class);
    $mock->shouldReceive('generatePresignedDownloadUrl')
        ->withArgs(function (string $key, int $minutes) use (&$captured): bool {
            $captured = $minutes;

            return true;
        })
        ->andReturn('https://s3.example/x');
    app()->instance(CloudStorageService::class, $mock);

    Mediator::dispatch(app(GenerateDocumentViewUrl::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'documentId' => Id::fromString($this->document->ulid),
        'actorId' => Id::fromString($this->member->ulid),
        'expiryMinutes' => 5,
    ]));

    expect($captured)->toBe(5);
});

it('dispatches DocumentViewUrlIssued with document + business + actor ids', function () {
    bindMockStorage('https://s3.example/x');
    Event::fake([DocumentViewUrlIssued::class]);

    Mediator::dispatch(app(GenerateDocumentViewUrl::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'documentId' => Id::fromString($this->document->ulid),
        'actorId' => Id::fromString($this->member->ulid),
    ]));

    Event::assertDispatched(
        DocumentViewUrlIssued::class,
        fn (DocumentViewUrlIssued $e) => $e->documentId->toString() === $this->document->ulid
            && $e->businessId->toString() === $this->business->ulid
            && $e->actorId->toString() === $this->member->ulid
    );
});

it('throws ModelNotFoundException when the document is in a different business', function () {
    bindMockStorage('https://s3.example/x');
    $other = Business::factory()->create();

    Mediator::dispatch(app(GenerateDocumentViewUrl::class, [
        'businessId' => Id::fromString($other->ulid),
        'documentId' => Id::fromString($this->document->ulid),
        'actorId' => Id::fromString($this->member->ulid),
    ]));
})->throws(ModelNotFoundException::class);

it('throws DocumentAccessDenied when the actor belongs to a different business', function () {
    bindMockStorage('https://s3.example/x');
    $stranger = User::create([
        'name' => 'Stranger',
        'email' => 's@x.com',
        'workos_id' => 'ws-1',
        'avatar' => '',
        'role' => UserRole::Owner->value,
        'business_id' => Business::factory()->create()->id,
    ]);

    Mediator::dispatch(app(GenerateDocumentViewUrl::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'documentId' => Id::fromString($this->document->ulid),
        'actorId' => Id::fromString($stranger->ulid),
    ]));
})->throws(DocumentAccessDenied::class);

it('allows a super_admin to view documents from any business', function () {
    bindMockStorage('https://s3.example/x');
    $admin = User::create([
        'name' => 'Admin',
        'email' => 'admin@x.com',
        'workos_id' => 'wsa-1',
        'avatar' => '',
        'role' => UserRole::SuperAdmin->value,
    ]);

    $result = Mediator::dispatch(app(GenerateDocumentViewUrl::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'documentId' => Id::fromString($this->document->ulid),
        'actorId' => Id::fromString($admin->ulid),
    ]));

    expect($result)->toBeInstanceOf(DocumentViewUrl::class);
});

it('does not expose the raw s3_key on the DTO', function () {
    bindMockStorage('https://s3.example/x');

    $result = Mediator::dispatch(app(GenerateDocumentViewUrl::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'documentId' => Id::fromString($this->document->ulid),
        'actorId' => Id::fromString($this->member->ulid),
    ]));

    expect(get_object_vars($result))->not->toHaveKey('s3Key')
        ->and(get_object_vars($result))->not->toHaveKey('s3_key');
});
