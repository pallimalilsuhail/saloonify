<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Businesses\Models\Business;
use App\Modules\Customers\DTOs\CustomerDetails;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\UseCases\GetCustomer\GetCustomer;
use AvoqadoDev\UseCase\Facades\Mediator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Shared\ValueObjects\Id;

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->customer = Customer::factory()->for($this->business)->create();
});

it('returns a CustomerDetails when ULID + business match', function () {
    $result = Mediator::dispatch(app(GetCustomer::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'customerId' => Id::fromString($this->customer->ulid),
    ]));

    expect($result)->toBeInstanceOf(CustomerDetails::class)
        ->and($result->id->toString())->toBe($this->customer->ulid)
        ->and($result->businessId->toString())->toBe($this->business->ulid)
        ->and($result->name)->toBe($this->customer->name)
        ->and($result->phone->toE164())->toBe($this->customer->phone)
        ->and($result->email?->toString())->toBe($this->customer->email);
});

it('includes the creator name when createdBy is set', function () {
    $user = User::create([
        'name' => 'Owner',
        'email' => 'o@x.com',
        'workos_id' => 'w1',
        'avatar' => '',
        'role' => UserRole::Owner->value,
        'business_id' => $this->business->id,
    ]);
    $this->customer->update(['created_by_id' => $user->id]);

    $result = Mediator::dispatch(app(GetCustomer::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'customerId' => Id::fromString($this->customer->ulid),
    ]));

    expect($result->createdById?->toString())->toBe($user->ulid)
        ->and($result->createdByName)->toBe('Owner');
});

it('leaves creator fields null when createdBy is unset', function () {
    $result = Mediator::dispatch(app(GetCustomer::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'customerId' => Id::fromString($this->customer->ulid),
    ]));

    expect($result->createdById)->toBeNull()
        ->and($result->createdByName)->toBeNull();
});

it('throws when the customer belongs to a different business', function () {
    $other = Business::factory()->create();

    Mediator::dispatch(app(GetCustomer::class, [
        'businessId' => Id::fromString($other->ulid),
        'customerId' => Id::fromString($this->customer->ulid),
    ]));
})->throws(ModelNotFoundException::class);

it('throws when the customer does not exist', function () {
    Mediator::dispatch(app(GetCustomer::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'customerId' => Id::generate(),
    ]));
})->throws(ModelNotFoundException::class);
