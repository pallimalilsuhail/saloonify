<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Businesses\Models\Business;
use App\Modules\Customers\DTOs\CustomerDetails;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\UseCases\GetCustomer\GetCustomer;
use App\Modules\Customers\UseCases\UpdateCustomer\UpdateCustomer;
use App\Modules\DocumentRequests\DTOs\IssuedUploadLink;
use App\Modules\DocumentRequests\Models\UploadSession;
use App\Modules\DocumentRequests\UseCases\GenerateUploadLink\GenerateUploadLink;
use App\Modules\DocumentRequests\UseCases\RegenerateUploadLink\RegenerateUploadLink;
use App\Modules\DocumentRequests\UseCases\RevokeUploadLink\RevokeUploadLink;
use App\Modules\Documents\Enums\DocumentStatus;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\UseCases\DeleteDocument\DeleteDocument;
use AvoqadoDev\UseCase\Facades\Mediator;
use Carbon\CarbonImmutable;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;
use Livewire\Livewire;
use Shared\ValueObjects\Email;
use Shared\ValueObjects\Id;
use Shared\ValueObjects\PhoneNumber;

beforeEach(function () {
    $this->withoutMiddleware(ValidateSessionWithWorkOS::class);

    $this->business = Business::factory()->create();
    $this->member = User::create([
        'name' => 'Member',
        'email' => 'm@x.com',
        'workos_id' => 'wm-1',
        'avatar' => '',
        'role' => UserRole::Member->value,
        'business_id' => $this->business->id,
    ]);
    $this->customer = Customer::factory()->for($this->business)->create([
        'name' => 'Original',
        'phone' => '+971501112233',
        'email' => 'original@example.com',
    ]);

    $this->actingAs($this->member);
});

function fakeDetail(string $ulid, string $businessUlid): CustomerDetails
{
    return new CustomerDetails(
        id: Id::fromString($ulid),
        businessId: Id::fromString($businessUlid),
        name: 'Original',
        phone: PhoneNumber::fromE164('+971501112233'),
        email: new Email('original@example.com'),
        createdById: null,
        createdByName: null,
        createdAt: CarbonImmutable::now()->subDays(2),
        updatedAt: CarbonImmutable::now(),
    );
}

it('dispatches UpdateCustomer with name + phone + email VOs from the form', function () {
    $detail = fakeDetail($this->customer->ulid, $this->business->ulid);
    Mediator::fake([GetCustomer::class => $detail]);
    $fake = Mediator::fake(UpdateCustomer::class, Id::fromString($this->customer->ulid));

    Livewire::test('pages::customers.show', ['ulid' => $this->customer->ulid])
        ->call('openEdit')
        ->set('name', 'Updated')
        ->set('phone', '+971507778899')
        ->set('email', 'updated@example.com')
        ->call('update')
        ->assertHasNoErrors();

    $fake->assertDispatched(
        UpdateCustomer::class,
        fn (UpdateCustomer $cmd) => $cmd->businessId->toString() === $this->business->ulid
            && $cmd->customerId->toString() === $this->customer->ulid
            && $cmd->name === 'Updated'
            && $cmd->phone instanceof PhoneNumber
            && $cmd->phone->toE164() === '+971507778899'
            && $cmd->email instanceof Email
            && $cmd->email->toString() === 'updated@example.com'
    );
});

it('passes email=false when the clear-email checkbox is set', function () {
    $detail = fakeDetail($this->customer->ulid, $this->business->ulid);
    Mediator::fake([GetCustomer::class => $detail]);
    $fake = Mediator::fake(UpdateCustomer::class, Id::fromString($this->customer->ulid));

    Livewire::test('pages::customers.show', ['ulid' => $this->customer->ulid])
        ->call('openEdit')
        ->set('clearEmail', true)
        ->call('update')
        ->assertHasNoErrors();

    $fake->assertDispatched(
        UpdateCustomer::class,
        fn (UpdateCustomer $cmd) => $cmd->email === false
    );
});

it('does not dispatch UpdateCustomer when validation fails', function () {
    $detail = fakeDetail($this->customer->ulid, $this->business->ulid);
    Mediator::fake([GetCustomer::class => $detail]);
    $fake = Mediator::fake(UpdateCustomer::class, Id::fromString($this->customer->ulid));

    Livewire::test('pages::customers.show', ['ulid' => $this->customer->ulid])
        ->call('openEdit')
        ->set('name', '')
        ->call('update')
        ->assertHasErrors(['name']);

    $fake->assertNotDispatched(UpdateCustomer::class);
});

it('renders the show page for a business member', function () {
    $this->get(route('customers.show', ['ulid' => $this->customer->ulid]))
        ->assertOk()
        ->assertSee($this->customer->name)
        ->assertSee('Documents')
        ->assertSee('Upload links')
        ->assertSee('Generate link');
});

it('renders confirmed documents in the documents panel', function () {
    $session = UploadSession::factory()->create([
        'business_id' => $this->business->id,
        'customer_id' => $this->customer->id,
    ]);
    Document::factory()->create([
        'business_id' => $this->business->id,
        'customer_id' => $this->customer->id,
        'upload_session_id' => $session->id,
        'original_name' => 'passport.pdf',
        'status' => DocumentStatus::Confirmed->value,
        'uploaded_at' => now(),
    ]);

    $this->get(route('customers.show', ['ulid' => $this->customer->ulid]))
        ->assertOk()
        ->assertSee('passport.pdf');
});

it('dispatches DeleteDocument with business + document + actor when an owner clicks delete', function () {
    $owner = User::create([
        'name' => 'Owner',
        'email' => 'o@x.com',
        'workos_id' => 'wo-1',
        'avatar' => '',
        'role' => UserRole::Owner->value,
        'business_id' => $this->business->id,
    ]);
    $this->actingAs($owner);

    $detail = fakeDetail($this->customer->ulid, $this->business->ulid);
    Mediator::fake([GetCustomer::class => $detail]);

    $session = UploadSession::factory()->create([
        'business_id' => $this->business->id,
        'customer_id' => $this->customer->id,
    ]);
    $doc = Document::factory()->create([
        'business_id' => $this->business->id,
        'customer_id' => $this->customer->id,
        'upload_session_id' => $session->id,
    ]);

    $fake = Mediator::fake(DeleteDocument::class, Id::fromString($doc->ulid));

    Livewire::test('pages::customers.show', ['ulid' => $this->customer->ulid])
        ->call('deleteDocument', $doc->ulid);

    $fake->assertDispatched(
        DeleteDocument::class,
        fn (DeleteDocument $cmd) => $cmd->businessId->toString() === $this->business->ulid
            && $cmd->documentId->toString() === $doc->ulid
            && $cmd->actorId?->toString() === $owner->ulid
    );
});

it('dispatches GenerateUploadLink with the customer + business + actor when the button is clicked', function () {
    $detail = fakeDetail($this->customer->ulid, $this->business->ulid);
    Mediator::fake([GetCustomer::class => $detail]);

    $fake = Mediator::fake(GenerateUploadLink::class, new IssuedUploadLink(
        sessionId: Id::generate(),
        rawToken: 'sample-token',
        url: 'http://test.local/u/sample-token',
        expiresAt: CarbonImmutable::now()->addHour(),
    ));

    Livewire::test('pages::customers.show', ['ulid' => $this->customer->ulid])
        ->call('generateUploadLink')
        ->assertSet('generatedUrl', 'http://test.local/u/sample-token');

    $fake->assertDispatched(
        GenerateUploadLink::class,
        fn (GenerateUploadLink $cmd) => $cmd->businessId->toString() === $this->business->ulid
            && $cmd->customerId->toString() === $this->customer->ulid
            && $cmd->generatedById?->toString() === $this->member->ulid
    );
});

it('returns 404 when the customer is in a different business', function () {
    $otherBusiness = Business::factory()->create();
    $otherCustomer = Customer::factory()->for($otherBusiness)->create();

    $this->get(route('customers.show', ['ulid' => $otherCustomer->ulid]))
        ->assertNotFound();
});

it('dispatches RegenerateUploadLink with the customer + actor when the regenerate button is clicked', function () {
    $detail = fakeDetail($this->customer->ulid, $this->business->ulid);
    Mediator::fake([GetCustomer::class => $detail]);

    $fake = Mediator::fake(RegenerateUploadLink::class, new IssuedUploadLink(
        sessionId: Id::generate(),
        rawToken: 'regenerated-token',
        url: 'http://test.local/u/regenerated-token',
        expiresAt: CarbonImmutable::now()->addHour(),
    ));

    Livewire::test('pages::customers.show', ['ulid' => $this->customer->ulid])
        ->call('regenerateUploadLink')
        ->assertSet('generatedUrl', 'http://test.local/u/regenerated-token');

    $fake->assertDispatched(
        RegenerateUploadLink::class,
        fn (RegenerateUploadLink $cmd) => $cmd->businessId->toString() === $this->business->ulid
            && $cmd->customerId->toString() === $this->customer->ulid
            && $cmd->generatedById?->toString() === $this->member->ulid
    );
});

it('dispatches RevokeUploadLink with the session ULID + actor when the revoke button is clicked', function () {
    $detail = fakeDetail($this->customer->ulid, $this->business->ulid);
    Mediator::fake([GetCustomer::class => $detail]);

    $session = UploadSession::factory()->create([
        'business_id' => $this->business->id,
        'customer_id' => $this->customer->id,
    ]);

    $fake = Mediator::fake(RevokeUploadLink::class, Id::fromString($session->ulid));

    Livewire::test('pages::customers.show', ['ulid' => $this->customer->ulid])
        ->call('revokeUploadLink', $session->ulid);

    $fake->assertDispatched(
        RevokeUploadLink::class,
        fn (RevokeUploadLink $cmd) => $cmd->sessionId->toString() === $session->ulid
            && $cmd->revokedById?->toString() === $this->member->ulid
    );
});
