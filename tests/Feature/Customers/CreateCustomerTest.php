<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Businesses\Models\Business;
use App\Modules\Customers\Events\CustomerCreated;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\UseCases\CreateCustomer\CreateCustomer;
use AvoqadoDev\UseCase\BusinessRules\Exceptions\BusinessRuleException;
use AvoqadoDev\UseCase\Facades\Mediator;
use Illuminate\Support\Facades\Event;
use Shared\ValueObjects\Email;
use Shared\ValueObjects\Id;
use Shared\ValueObjects\PhoneNumber;

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->actor = User::create([
        'name' => 'Owner',
        'email' => 'o@example.com',
        'workos_id' => 'wo-1',
        'avatar' => '',
        'role' => UserRole::Owner->value,
        'business_id' => $this->business->id,
    ]);
});

it('creates a customer with phone + email + creator and returns its Id', function () {
    Event::fake([CustomerCreated::class]);

    $id = Mediator::dispatch(app(CreateCustomer::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'name' => 'Alice',
        'phone' => new PhoneNumber('+971501234567'),
        'email' => new Email('alice@example.com'),
        'createdById' => Id::fromString($this->actor->ulid),
    ]));

    $customer = Customer::where('ulid', $id->toString())->firstOrFail();

    expect($customer->name)->toBe('Alice')
        ->and($customer->phone)->toBe('+971501234567')
        ->and($customer->email)->toBe('alice@example.com')
        ->and($customer->business_id)->toBe($this->business->id)
        ->and($customer->created_by_id)->toBe($this->actor->id);

    Event::assertDispatched(
        CustomerCreated::class,
        fn (CustomerCreated $e) => $e->customerId->toString() === $id->toString()
            && $e->businessId->toString() === $this->business->ulid
            && $e->createdById?->toString() === $this->actor->ulid
    );
});

it('allows email to be omitted', function () {
    $id = Mediator::dispatch(app(CreateCustomer::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'name' => 'NoEmail',
        'phone' => new PhoneNumber('+971501112233'),
    ]));

    expect(Customer::where('ulid', $id->toString())->value('email'))->toBeNull();
});

it('rejects when phone already exists in the same business', function () {
    Customer::factory()->for($this->business)->create([
        'phone' => '+971501234567',
        'email' => 'first@example.com',
    ]);

    Mediator::dispatch(app(CreateCustomer::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'name' => 'Dup',
        'phone' => new PhoneNumber('+971501234567'),
    ]));
})->throws(BusinessRuleException::class, 'phone +971501234567 already exists');

it('rejects when email already exists in the same business', function () {
    Customer::factory()->for($this->business)->create([
        'phone' => '+971501112200',
        'email' => 'taken@example.com',
    ]);

    Mediator::dispatch(app(CreateCustomer::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'name' => 'Dup',
        'phone' => new PhoneNumber('+971501112299'),
        'email' => new Email('taken@example.com'),
    ]));
})->throws(BusinessRuleException::class, 'email taken@example.com already exists');

it('allows the same phone in a different business', function () {
    $other = Business::factory()->create();
    Customer::factory()->for($other)->create(['phone' => '+971501234567']);

    $id = Mediator::dispatch(app(CreateCustomer::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'name' => 'Same phone, different biz',
        'phone' => new PhoneNumber('+971501234567'),
    ]));

    expect(Customer::where('ulid', $id->toString())->exists())->toBeTrue();
});
