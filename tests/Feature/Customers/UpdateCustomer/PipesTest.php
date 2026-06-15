<?php

declare(strict_types=1);

use App\Modules\Businesses\Models\Business;
use App\Modules\Common\Services\EventCollector;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\UseCases\UpdateCustomer\Pipes\LoadCustomer;
use App\Modules\Customers\UseCases\UpdateCustomer\Pipes\UpdateBasicInfo;
use App\Modules\Customers\UseCases\UpdateCustomer\Pipes\UpdateContact;
use App\Modules\Customers\UseCases\UpdateCustomer\Pipes\ValidateRules;
use App\Modules\Customers\UseCases\UpdateCustomer\UpdateCustomer;
use App\Modules\Customers\UseCases\UpdateCustomer\UpdateCustomerPassable;
use AvoqadoDev\UseCase\BusinessRules\Contracts\GuardsRules;
use AvoqadoDev\UseCase\BusinessRules\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Shared\ValueObjects\Email;
use Shared\ValueObjects\Id;
use Shared\ValueObjects\PhoneNumber;

function makePassable(UpdateCustomer $request): UpdateCustomerPassable
{
    return new UpdateCustomerPassable(
        request: $request,
        customer: null,
        eventCollector: new EventCollector,
        guardsRules: app(GuardsRules::class),
    );
}

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->customer = Customer::factory()->for($this->business)->create([
        'name' => 'Original',
        'phone' => '+971501112233',
        'email' => 'original@example.com',
    ]);
});

// ---------- LoadCustomer ----------

it('LoadCustomer hydrates the passable with the matching customer', function () {
    $passable = makePassable(new UpdateCustomer(
        businessId: Id::fromString($this->business->ulid),
        customerId: Id::fromString($this->customer->ulid),
    ));

    (new LoadCustomer)->handle($passable, fn ($p) => $p);

    expect($passable->customer)->not->toBeNull()
        ->and($passable->customer->id)->toBe($this->customer->id);
});

it('LoadCustomer throws when the customer is in a different business (BelongsToBusiness)', function () {
    $other = Business::factory()->create();
    $passable = makePassable(new UpdateCustomer(
        businessId: Id::fromString($other->ulid),
        customerId: Id::fromString($this->customer->ulid),
    ));

    (new LoadCustomer)->handle($passable, fn ($p) => $p);
})->throws(ModelNotFoundException::class);

// ---------- ValidateRules ----------

it('ValidateRules passes when phone + email are unchanged', function () {
    $passable = makePassable(new UpdateCustomer(
        businessId: Id::fromString($this->business->ulid),
        customerId: Id::fromString($this->customer->ulid),
        phone: new PhoneNumber('+971501112233'),
        email: new Email('original@example.com'),
    ));
    $passable->customer = $this->customer;

    $result = (new ValidateRules)->handle($passable, fn ($p) => 'next-called');

    expect($result)->toBe('next-called');
});

it('ValidateRules throws when changing phone to one already taken in the business', function () {
    Customer::factory()->for($this->business)->create(['phone' => '+971507770000']);

    $passable = makePassable(new UpdateCustomer(
        businessId: Id::fromString($this->business->ulid),
        customerId: Id::fromString($this->customer->ulid),
        phone: new PhoneNumber('+971507770000'),
    ));
    $passable->customer = $this->customer;

    (new ValidateRules)->handle($passable, fn ($p) => $p);
})->throws(BusinessRuleException::class);

// ---------- UpdateBasicInfo ----------

it('UpdateBasicInfo writes name and records the change', function () {
    $passable = makePassable(new UpdateCustomer(
        businessId: Id::fromString($this->business->ulid),
        customerId: Id::fromString($this->customer->ulid),
        name: 'Renamed',
    ));
    $passable->customer = $this->customer;

    (new UpdateBasicInfo)->handle($passable, fn ($p) => $p);

    expect($this->customer->fresh()->name)->toBe('Renamed')
        ->and($passable->changes)->toMatchArray(['name' => ['old' => 'Original', 'new' => 'Renamed']]);
});

it('UpdateBasicInfo records nothing when name is unchanged', function () {
    $passable = makePassable(new UpdateCustomer(
        businessId: Id::fromString($this->business->ulid),
        customerId: Id::fromString($this->customer->ulid),
        name: 'Original',
    ));
    $passable->customer = $this->customer;

    (new UpdateBasicInfo)->handle($passable, fn ($p) => $p);

    expect($passable->changes)->toBe([]);
});

// ---------- UpdateContact ----------

it('UpdateContact writes phone + email and records both changes', function () {
    $passable = makePassable(new UpdateCustomer(
        businessId: Id::fromString($this->business->ulid),
        customerId: Id::fromString($this->customer->ulid),
        phone: new PhoneNumber('+971507778899'),
        email: new Email('new@example.com'),
    ));
    $passable->customer = $this->customer;

    (new UpdateContact)->handle($passable, fn ($p) => $p);

    $fresh = $this->customer->fresh();
    expect($fresh->phone)->toBe('+971507778899')
        ->and($fresh->email)->toBe('new@example.com')
        ->and($passable->changes)->toHaveKeys(['phone', 'email']);
});

it('UpdateContact clears email when email is false', function () {
    $passable = makePassable(new UpdateCustomer(
        businessId: Id::fromString($this->business->ulid),
        customerId: Id::fromString($this->customer->ulid),
        email: false,
    ));
    $passable->customer = $this->customer;

    (new UpdateContact)->handle($passable, fn ($p) => $p);

    expect($this->customer->fresh()->email)->toBeNull()
        ->and($passable->changes['email'])->toBe(['old' => 'original@example.com', 'new' => null]);
});

it('UpdateContact short-circuits when no contact fields are present', function () {
    $passable = makePassable(new UpdateCustomer(
        businessId: Id::fromString($this->business->ulid),
        customerId: Id::fromString($this->customer->ulid),
        name: 'Only name',
    ));
    $passable->customer = $this->customer;

    (new UpdateContact)->handle($passable, fn ($p) => $p);

    expect($passable->changes)->toBe([]);
});
