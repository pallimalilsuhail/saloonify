<?php

declare(strict_types=1);

use App\Modules\Businesses\Models\Business;
use App\Modules\Customers\Events\CustomerUpdated;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\UseCases\UpdateCustomer\UpdateCustomer;
use AvoqadoDev\UseCase\BusinessRules\Exceptions\BusinessRuleException;
use AvoqadoDev\UseCase\Facades\Mediator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Event;
use Shared\ValueObjects\Email;
use Shared\ValueObjects\Id;
use Shared\ValueObjects\PhoneNumber;

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->customer = Customer::factory()->for($this->business)->create([
        'name' => 'Original',
        'phone' => '+971501112233',
        'email' => 'original@example.com',
    ]);
});

it('updates name + phone + email and dispatches a single CustomerUpdated event with all changes', function () {
    Event::fake([CustomerUpdated::class]);

    $id = Mediator::dispatch(app(UpdateCustomer::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'customerId' => Id::fromString($this->customer->ulid),
        'name' => 'Updated',
        'phone' => new PhoneNumber('+971507778899'),
        'email' => new Email('updated@example.com'),
    ]));

    $fresh = $this->customer->fresh();
    expect($fresh->name)->toBe('Updated')
        ->and($fresh->phone)->toBe('+971507778899')
        ->and($fresh->email)->toBe('updated@example.com');

    Event::assertDispatched(
        CustomerUpdated::class,
        fn (CustomerUpdated $e) => $e->customerId->toString() === $id->toString()
            && $e->changes['name']['old'] === 'Original'
            && $e->changes['name']['new'] === 'Updated'
            && $e->changes['phone']['new'] === '+971507778899'
            && $e->changes['email']['new'] === 'updated@example.com'
    );
});

it('skips fields that are unchanged and dispatches no event when nothing changes', function () {
    Event::fake([CustomerUpdated::class]);

    Mediator::dispatch(app(UpdateCustomer::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'customerId' => Id::fromString($this->customer->ulid),
        'name' => 'Original',
    ]));

    Event::assertNotDispatched(CustomerUpdated::class);
});

it('clears email when email is set to false', function () {
    Mediator::dispatch(app(UpdateCustomer::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'customerId' => Id::fromString($this->customer->ulid),
        'email' => false,
    ]));

    expect($this->customer->fresh()->email)->toBeNull();
});

it('rejects when phone is taken by another customer in the same business', function () {
    Customer::factory()->for($this->business)->create(['phone' => '+971507770000']);

    Mediator::dispatch(app(UpdateCustomer::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'customerId' => Id::fromString($this->customer->ulid),
        'phone' => new PhoneNumber('+971507770000'),
    ]));
})->throws(BusinessRuleException::class);

it('rejects when email is taken by another customer in the same business', function () {
    Customer::factory()->for($this->business)->create(['email' => 'taken@example.com']);

    Mediator::dispatch(app(UpdateCustomer::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'customerId' => Id::fromString($this->customer->ulid),
        'email' => new Email('taken@example.com'),
    ]));
})->throws(BusinessRuleException::class);

it('allows keeping the same phone (uniqueness check ignores self)', function () {
    Mediator::dispatch(app(UpdateCustomer::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'customerId' => Id::fromString($this->customer->ulid),
        'name' => 'Same phone update',
        'phone' => new PhoneNumber('+971501112233'),
    ]));

    expect($this->customer->fresh()->name)->toBe('Same phone update');
});

it('throws when the customer does not belong to the supplied business', function () {
    $other = Business::factory()->create();

    Mediator::dispatch(app(UpdateCustomer::class, [
        'businessId' => Id::fromString($other->ulid),
        'customerId' => Id::fromString($this->customer->ulid),
        'name' => 'Cross-tenant attempt',
    ]));
})->throws(ModelNotFoundException::class);
