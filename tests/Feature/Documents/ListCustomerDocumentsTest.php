<?php

declare(strict_types=1);

use App\Modules\Businesses\Models\Business;
use App\Modules\Customers\Models\Customer;
use App\Modules\DocumentRequests\Models\UploadSession;
use App\Modules\Documents\DTOs\DocumentSummary;
use App\Modules\Documents\Enums\DocumentStatus;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\UseCases\ListCustomerDocuments\ListCustomerDocuments;
use AvoqadoDev\UseCase\Facades\Mediator;
use Shared\ValueObjects\Id;

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->customer = Customer::factory()->for($this->business)->create();
    $this->session = UploadSession::factory()->create([
        'business_id' => $this->business->id,
        'customer_id' => $this->customer->id,
    ]);
});

it('returns a paginator of DocumentSummary scoped to the customer + business', function () {
    Document::factory()->count(3)->create([
        'business_id' => $this->business->id,
        'customer_id' => $this->customer->id,
        'upload_session_id' => $this->session->id,
        'status' => DocumentStatus::Confirmed->value,
        'uploaded_at' => now(),
    ]);

    $otherCustomer = Customer::factory()->for($this->business)->create();
    Document::factory()->count(2)->create([
        'business_id' => $this->business->id,
        'customer_id' => $otherCustomer->id,
        'upload_session_id' => $this->session->id,
        'status' => DocumentStatus::Confirmed->value,
        'uploaded_at' => now(),
    ]);

    $result = Mediator::dispatch(app(ListCustomerDocuments::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'customerId' => Id::fromString($this->customer->ulid),
    ]));

    expect($result->total())->toBe(3)
        ->and($result->items()[0])->toBeInstanceOf(DocumentSummary::class);
});

it('only returns confirmed documents (skips Pending + VirusFlagged)', function () {
    Document::factory()->create([
        'business_id' => $this->business->id,
        'customer_id' => $this->customer->id,
        'upload_session_id' => $this->session->id,
        'status' => DocumentStatus::Confirmed->value,
        'uploaded_at' => now(),
    ]);
    Document::factory()->count(2)->create([
        'business_id' => $this->business->id,
        'customer_id' => $this->customer->id,
        'upload_session_id' => $this->session->id,
        'status' => DocumentStatus::Pending->value,
    ]);
    Document::factory()->create([
        'business_id' => $this->business->id,
        'customer_id' => $this->customer->id,
        'upload_session_id' => $this->session->id,
        'status' => DocumentStatus::VirusFlagged->value,
    ]);

    $result = Mediator::dispatch(app(ListCustomerDocuments::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'customerId' => Id::fromString($this->customer->ulid),
    ]));

    expect($result->total())->toBe(1);
});

it('orders most recently uploaded first', function () {
    $older = Document::factory()->create([
        'business_id' => $this->business->id,
        'customer_id' => $this->customer->id,
        'upload_session_id' => $this->session->id,
        'status' => DocumentStatus::Confirmed->value,
        'uploaded_at' => now()->subHour(),
    ]);
    $newer = Document::factory()->create([
        'business_id' => $this->business->id,
        'customer_id' => $this->customer->id,
        'upload_session_id' => $this->session->id,
        'status' => DocumentStatus::Confirmed->value,
        'uploaded_at' => now(),
    ]);

    $result = Mediator::dispatch(app(ListCustomerDocuments::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'customerId' => Id::fromString($this->customer->ulid),
    ]));

    expect($result->items()[0]->id->toString())->toBe($newer->ulid)
        ->and($result->items()[1]->id->toString())->toBe($older->ulid);
});

it('filters by uploadSessionId when supplied', function () {
    $otherSession = UploadSession::factory()->create([
        'business_id' => $this->business->id,
        'customer_id' => $this->customer->id,
    ]);

    Document::factory()->count(2)->create([
        'business_id' => $this->business->id,
        'customer_id' => $this->customer->id,
        'upload_session_id' => $this->session->id,
        'status' => DocumentStatus::Confirmed->value,
        'uploaded_at' => now(),
    ]);
    Document::factory()->create([
        'business_id' => $this->business->id,
        'customer_id' => $this->customer->id,
        'upload_session_id' => $otherSession->id,
        'status' => DocumentStatus::Confirmed->value,
        'uploaded_at' => now(),
    ]);

    $result = Mediator::dispatch(app(ListCustomerDocuments::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'customerId' => Id::fromString($this->customer->ulid),
        'uploadSessionId' => Id::fromString($otherSession->ulid),
    ]));

    expect($result->total())->toBe(1);
});

it('refuses to leak across businesses (BelongsToBusiness)', function () {
    Document::factory()->create([
        'business_id' => $this->business->id,
        'customer_id' => $this->customer->id,
        'upload_session_id' => $this->session->id,
        'status' => DocumentStatus::Confirmed->value,
        'uploaded_at' => now(),
    ]);

    $otherBusiness = Business::factory()->create();

    $result = Mediator::dispatch(app(ListCustomerDocuments::class, [
        'businessId' => Id::fromString($otherBusiness->ulid),
        'customerId' => Id::fromString($this->customer->ulid),
    ]));

    expect($result->total())->toBe(0);
});

it('respects perPage', function () {
    Document::factory()->count(7)->create([
        'business_id' => $this->business->id,
        'customer_id' => $this->customer->id,
        'upload_session_id' => $this->session->id,
        'status' => DocumentStatus::Confirmed->value,
        'uploaded_at' => now(),
    ]);

    $result = Mediator::dispatch(app(ListCustomerDocuments::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'customerId' => Id::fromString($this->customer->ulid),
        'perPage' => 5,
    ]));

    expect($result->perPage())->toBe(5)
        ->and($result->total())->toBe(7)
        ->and($result->lastPage())->toBe(2);
});
