<?php

declare(strict_types=1);

use App\Modules\Businesses\Models\Business;
use App\Modules\Customers\BusinessRules\EmailUniquePerBusiness;
use App\Modules\Customers\Models\Customer;
use Shared\ValueObjects\Email;
use Shared\ValueObjects\Id;

beforeEach(function () {
    $this->business = Business::factory()->create();
});

it('passes when no customer in the business has the email', function () {
    $rule = new EmailUniquePerBusiness(
        Id::fromString($this->business->ulid),
        new Email('alice@example.com'),
    );

    expect($rule->passes())->toBeTrue();
});

it('fails when another customer in the same business already has the email', function () {
    Customer::factory()->for($this->business)->create(['email' => 'alice@example.com']);

    $rule = new EmailUniquePerBusiness(
        Id::fromString($this->business->ulid),
        new Email('alice@example.com'),
    );

    expect($rule->passes())->toBeFalse();
});

it('passes when the matching customer is the ignored one', function () {
    $existing = Customer::factory()->for($this->business)->create(['email' => 'alice@example.com']);

    $rule = new EmailUniquePerBusiness(
        Id::fromString($this->business->ulid),
        new Email('alice@example.com'),
        Id::fromString($existing->ulid),
    );

    expect($rule->passes())->toBeTrue();
});

it('passes when email matches a customer in a different business', function () {
    $other = Business::factory()->create();
    Customer::factory()->for($other)->create(['email' => 'alice@example.com']);

    $rule = new EmailUniquePerBusiness(
        Id::fromString($this->business->ulid),
        new Email('alice@example.com'),
    );

    expect($rule->passes())->toBeTrue();
});

it('exposes a stable code and includes email in context', function () {
    $rule = new EmailUniquePerBusiness(
        Id::fromString($this->business->ulid),
        new Email('alice@example.com'),
    );

    expect($rule->code())->toBe('customer.email.duplicate')
        ->and($rule->context())->toBe(['email' => 'alice@example.com']);
});
