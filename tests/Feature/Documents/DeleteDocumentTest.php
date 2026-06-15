<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Businesses\Models\Business;
use App\Modules\Customers\Models\Customer;
use App\Modules\DocumentRequests\Models\UploadSession;
use App\Modules\Documents\Events\DocumentDeleted;
use App\Modules\Documents\Exceptions\DocumentAccessDenied;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\UseCases\DeleteDocument\DeleteDocument;
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
});

function bindTaggingStorage(?array &$capturedTags = null, ?string &$capturedKey = null): void
{
    $mock = Mockery::mock(CloudStorageService::class);
    $mock->shouldReceive('tagObject')
        ->withArgs(function (string $key, array $tags) use (&$capturedKey, &$capturedTags): bool {
            $capturedKey = $key;
            $capturedTags = $tags;

            return true;
        });
    app()->instance(CloudStorageService::class, $mock);
}

it('soft-deletes the document, tags the S3 object pending-delete, dispatches DocumentDeleted', function () {
    Event::fake([DocumentDeleted::class]);
    bindTaggingStorage($capturedTags, $capturedKey);

    Mediator::dispatch(app(DeleteDocument::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'documentId' => Id::fromString($this->document->ulid),
        'actorId' => Id::fromString($this->owner->ulid),
    ]));

    expect(Document::find($this->document->id))->toBeNull()
        ->and(Document::withTrashed()->find($this->document->id))->not->toBeNull()
        ->and($capturedKey)->toBe($this->document->s3_key)
        ->and($capturedTags)->toBe(['pending-delete' => 'true']);

    Event::assertDispatched(
        DocumentDeleted::class,
        fn (DocumentDeleted $e) => $e->documentId->toString() === $this->document->ulid
            && $e->businessId->toString() === $this->business->ulid
            && $e->actorId->toString() === $this->owner->ulid
    );
});

it('rejects a member (owner-only)', function () {
    bindTaggingStorage();

    Mediator::dispatch(app(DeleteDocument::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'documentId' => Id::fromString($this->document->ulid),
        'actorId' => Id::fromString($this->member->ulid),
    ]));
})->throws(DocumentAccessDenied::class);

it('rejects an owner from a different business', function () {
    bindTaggingStorage();
    $stranger = User::create([
        'name' => 'Stranger',
        'email' => 's@x.com',
        'workos_id' => 'ws-1',
        'avatar' => '',
        'role' => UserRole::Owner->value,
        'business_id' => Business::factory()->create()->id,
    ]);

    Mediator::dispatch(app(DeleteDocument::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'documentId' => Id::fromString($this->document->ulid),
        'actorId' => Id::fromString($stranger->ulid),
    ]));
})->throws(DocumentAccessDenied::class);

it('throws ModelNotFoundException for cross-business document', function () {
    bindTaggingStorage();
    $other = Business::factory()->create();

    Mediator::dispatch(app(DeleteDocument::class, [
        'businessId' => Id::fromString($other->ulid),
        'documentId' => Id::fromString($this->document->ulid),
        'actorId' => Id::fromString($this->owner->ulid),
    ]));
})->throws(ModelNotFoundException::class);

it('allows super_admin to delete any business document', function () {
    bindTaggingStorage();
    $admin = User::create([
        'name' => 'Admin',
        'email' => 'admin@x.com',
        'workos_id' => 'wsa-1',
        'avatar' => '',
        'role' => UserRole::SuperAdmin->value,
    ]);

    Mediator::dispatch(app(DeleteDocument::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'documentId' => Id::fromString($this->document->ulid),
        'actorId' => Id::fromString($admin->ulid),
    ]));

    expect(Document::find($this->document->id))->toBeNull();
});
