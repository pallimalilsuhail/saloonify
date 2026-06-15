<?php

declare(strict_types=1);

use App\Modules\Businesses\Models\Business;
use App\Modules\Customers\DTOs\CustomerSummary;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\UseCases\ListCustomers\ListCustomers;
use AvoqadoDev\UseCase\Facades\Mediator;
use Shared\ValueObjects\Id;

beforeEach(function () {
    $this->business = Business::factory()->create();
});

it('returns a paginator of CustomerSummary scoped to the business', function () {
    Customer::factory()->for($this->business)->count(3)->create();
    $other = Business::factory()->create();
    Customer::factory()->for($other)->count(2)->create();

    $result = Mediator::dispatch(app(ListCustomers::class, [
        'businessId' => Id::fromString($this->business->ulid),
    ]));

    expect($result->total())->toBe(3)
        ->and($result->items()[0])->toBeInstanceOf(CustomerSummary::class);
});

it('paginates with the supplied perPage', function () {
    Customer::factory()->for($this->business)->count(7)->create();

    $result = Mediator::dispatch(app(ListCustomers::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'perPage' => 5,
    ]));

    expect($result->perPage())->toBe(5)
        ->and($result->total())->toBe(7)
        ->and($result->lastPage())->toBe(2);
});

it('orders most recent first', function () {
    $older = Customer::factory()->for($this->business)->create(['created_at' => now()->subDays(2)]);
    $newer = Customer::factory()->for($this->business)->create(['created_at' => now()]);

    $result = Mediator::dispatch(app(ListCustomers::class, [
        'businessId' => Id::fromString($this->business->ulid),
    ]));

    expect($result->items()[0]->id->toString())->toBe($newer->ulid)
        ->and($result->items()[1]->id->toString())->toBe($older->ulid);
});

it('searches by name', function () {
    Customer::factory()->for($this->business)->create(['name' => 'Alice Wonderland']);
    Customer::factory()->for($this->business)->create(['name' => 'Bob Smith']);

    $result = Mediator::dispatch(app(ListCustomers::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'search' => 'wonder',
    ]));

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->name)->toBe('Alice Wonderland');
});

it('searches by phone', function () {
    Customer::factory()->for($this->business)->create(['phone' => '+971501234567']);
    Customer::factory()->for($this->business)->create(['phone' => '+971507778899']);

    $result = Mediator::dispatch(app(ListCustomers::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'search' => '777',
    ]));

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->phone->toE164())->toBe('+971507778899');
});

it('searches by email', function () {
    Customer::factory()->for($this->business)->create(['email' => 'alice@example.com']);
    Customer::factory()->for($this->business)->create(['email' => 'bob@example.com']);

    $result = Mediator::dispatch(app(ListCustomers::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'search' => 'alice',
    ]));

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->email?->toString())->toBe('alice@example.com');
});

it('returns an empty paginator when nothing matches', function () {
    Customer::factory()->for($this->business)->count(2)->create();

    $result = Mediator::dispatch(app(ListCustomers::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'search' => 'no-such-thing',
    ]));

    expect($result->total())->toBe(0);
});
