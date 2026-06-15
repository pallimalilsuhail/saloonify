<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Businesses\Models\Business;
use App\Modules\Customers\Models\Customer;
use App\Modules\DocumentRequests\Models\UploadSession;
use App\Modules\Documents\DTOs\DocumentViewUrl;
use App\Modules\Documents\Events\DocumentDownloaded;
use App\Modules\Documents\Exceptions\DocumentAccessDenied;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\UseCases\DownloadDocument\DownloadDocument;
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
        'original_name' => 'passport.pdf',
        's3_key' => 'business/biz/customer/cust/session/sess/passport.pdf',
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

function bindDownloadStorage(string $signedUrl, ?string &$capturedFilename = null, ?int &$capturedExpiry = null): void
{
    $mock = Mockery::mock(CloudStorageService::class);
    $mock->shouldReceive('generatePresignedDownloadUrl')
        ->withArgs(function (string $key, int $minutes, ?string $downloadAs) use (&$capturedFilename, &$capturedExpiry): bool {
            $capturedFilename = $downloadAs;
            $capturedExpiry = $minutes;

            return true;
        })
        ->andReturn($signedUrl);
    app()->instance(CloudStorageService::class, $mock);
}

it('returns a DocumentViewUrl DTO with the signed URL', function () {
    bindDownloadStorage('https://s3.example/x?signed=1');

    $result = Mediator::dispatch(app(DownloadDocument::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'documentId' => Id::fromString($this->document->ulid),
        'actorId' => Id::fromString($this->member->ulid),
    ]));

    expect($result)->toBeInstanceOf(DocumentViewUrl::class)
        ->and($result->url)->toBe('https://s3.example/x?signed=1');
});

it('passes the original filename to the cloud storage service for Content-Disposition', function () {
    bindDownloadStorage('https://s3.example/x', $capturedFilename);

    Mediator::dispatch(app(DownloadDocument::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'documentId' => Id::fromString($this->document->ulid),
        'actorId' => Id::fromString($this->member->ulid),
    ]));

    expect($capturedFilename)->toBe('passport.pdf');
});

it('forwards the requested expiryMinutes', function () {
    bindDownloadStorage('https://s3.example/x', $capturedFilename, $capturedExpiry);

    Mediator::dispatch(app(DownloadDocument::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'documentId' => Id::fromString($this->document->ulid),
        'actorId' => Id::fromString($this->member->ulid),
        'expiryMinutes' => 5,
    ]));

    expect($capturedExpiry)->toBe(5);
});

it('dispatches DocumentDownloaded with document + business + actor ids', function () {
    bindDownloadStorage('https://s3.example/x');
    Event::fake([DocumentDownloaded::class]);

    Mediator::dispatch(app(DownloadDocument::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'documentId' => Id::fromString($this->document->ulid),
        'actorId' => Id::fromString($this->member->ulid),
    ]));

    Event::assertDispatched(
        DocumentDownloaded::class,
        fn (DocumentDownloaded $e) => $e->documentId->toString() === $this->document->ulid
            && $e->businessId->toString() === $this->business->ulid
            && $e->actorId->toString() === $this->member->ulid
    );
});

it('throws ModelNotFoundException for cross-business document', function () {
    bindDownloadStorage('https://s3.example/x');
    $other = Business::factory()->create();

    Mediator::dispatch(app(DownloadDocument::class, [
        'businessId' => Id::fromString($other->ulid),
        'documentId' => Id::fromString($this->document->ulid),
        'actorId' => Id::fromString($this->member->ulid),
    ]));
})->throws(ModelNotFoundException::class);

it('throws DocumentAccessDenied for cross-business actor', function () {
    bindDownloadStorage('https://s3.example/x');
    $stranger = User::create([
        'name' => 'Stranger',
        'email' => 's@x.com',
        'workos_id' => 'ws-1',
        'avatar' => '',
        'role' => UserRole::Owner->value,
        'business_id' => Business::factory()->create()->id,
    ]);

    Mediator::dispatch(app(DownloadDocument::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'documentId' => Id::fromString($this->document->ulid),
        'actorId' => Id::fromString($stranger->ulid),
    ]));
})->throws(DocumentAccessDenied::class);

it('allows super_admin to download from any business', function () {
    bindDownloadStorage('https://s3.example/x');
    $admin = User::create([
        'name' => 'Admin',
        'email' => 'admin@x.com',
        'workos_id' => 'wsa-1',
        'avatar' => '',
        'role' => UserRole::SuperAdmin->value,
    ]);

    $result = Mediator::dispatch(app(DownloadDocument::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'documentId' => Id::fromString($this->document->ulid),
        'actorId' => Id::fromString($admin->ulid),
    ]));

    expect($result)->toBeInstanceOf(DocumentViewUrl::class);
});
