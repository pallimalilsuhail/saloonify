<?php

declare(strict_types=1);

use App\Modules\Businesses\Models\Business;
use App\Modules\Customers\BusinessRules\PhoneUniquePerBusiness;
use App\Modules\Customers\Models\Customer;
use Shared\ValueObjects\Id;
use Shared\ValueObjects\PhoneNumber;

beforeEach(function () {
    $this->business = Business::factory()->create();
});

it('passes when no customer in the business has the phone', function () {
    $rule = new PhoneUniquePerBusiness(
        Id::fromString($this->business->ulid),
        new PhoneNumber('+971501234567'),
    );

    expect($rule->passes())->toBeTrue();
});

it('fails when another customer in the same business already has the phone', function () {
    Customer::factory()->for($this->business)->create(['phone' => '+971501234567']);

    $rule = new PhoneUniquePerBusiness(
        Id::fromString($this->business->ulid),
        new PhoneNumber('+971501234567'),
    );

    expect($rule->passes())->toBeFalse();
});

it('passes when the matching customer is the ignored one (UpdateCustomer scenario)', function () {
    $existing = Customer::factory()->for($this->business)->create(['phone' => '+971501234567']);

    $rule = new PhoneUniquePerBusiness(
        Id::fromString($this->business->ulid),
        new PhoneNumber('+971501234567'),
        Id::fromString($existing->ulid),
    );

    expect($rule->passes())->toBeTrue();
});

it('passes when phone matches a customer in a different business', function () {
    $other = Business::factory()->create();
    Customer::factory()->for($other)->create(['phone' => '+971501234567']);

    $rule = new PhoneUniquePerBusiness(
        Id::fromString($this->business->ulid),
        new PhoneNumber('+971501234567'),
    );

    expect($rule->passes())->toBeTrue();
});

it('exposes a stable code and includes phone in context', function () {
    $rule = new PhoneUniquePerBusiness(
        Id::fromString($this->business->ulid),
        new PhoneNumber('+971501234567'),
    );

    expect($rule->code())->toBe('customer.phone.duplicate')
        ->and($rule->context())->toBe(['phone' => '+971501234567']);
});
