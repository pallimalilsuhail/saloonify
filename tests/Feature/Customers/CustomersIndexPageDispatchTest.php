<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Businesses\Models\Business;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\UseCases\CreateCustomer\CreateCustomer;
use App\Modules\Customers\UseCases\ListCustomers\ListCustomers;
use AvoqadoDev\UseCase\Facades\Mediator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
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
    $this->actingAs($this->member);
});

function fakeListPaginator(): LengthAwarePaginator
{
    return new LengthAwarePaginator(new Collection, 0, 25);
}

it('dispatches CreateCustomer with the form values + actor + business', function () {
    Mediator::fake([
        ListCustomers::class => fakeListPaginator(),
    ]);
    $fake = Mediator::fake(CreateCustomer::class, Id::generate());

    Livewire::test('pages::customers.index')
        ->call('openCreate')
        ->set('name', 'Alice Wonderland')
        ->set('phone', '+971501234567')
        ->set('email', 'alice@example.com')
        ->call('create')
        ->assertHasNoErrors();

    $fake->assertDispatched(
        CreateCustomer::class,
        fn (CreateCustomer $cmd) => $cmd->businessId->toString() === $this->business->ulid
            && $cmd->name === 'Alice Wonderland'
            && $cmd->phone instanceof PhoneNumber
            && $cmd->phone->toE164() === '+971501234567'
            && $cmd->email instanceof Email
            && $cmd->email->toString() === 'alice@example.com'
            && $cmd->createdById?->toString() === $this->member->ulid
    );
});

it('passes email=null when the email field is left blank', function () {
    Mediator::fake([
        ListCustomers::class => fakeListPaginator(),
    ]);
    $fake = Mediator::fake(CreateCustomer::class, Id::generate());

    Livewire::test('pages::customers.index')
        ->call('openCreate')
        ->set('name', 'NoEmail')
        ->set('phone', '+971501234567')
        ->set('email', '')
        ->call('create')
        ->assertHasNoErrors();

    $fake->assertDispatched(
        CreateCustomer::class,
        fn (CreateCustomer $cmd) => $cmd->email === null
    );
});

it('does not dispatch CreateCustomer when validation fails', function () {
    Mediator::fake([
        ListCustomers::class => fakeListPaginator(),
    ]);
    $fake = Mediator::fake(CreateCustomer::class, Id::generate());

    Livewire::test('pages::customers.index')
        ->call('openCreate')
        ->set('name', '')
        ->set('phone', '')
        ->call('create')
        ->assertHasErrors(['name', 'phone']);

    $fake->assertNotDispatched(CreateCustomer::class);
});

it('rejects an invalid phone with an inline error', function () {
    Mediator::fake([
        ListCustomers::class => fakeListPaginator(),
    ]);
    $fake = Mediator::fake(CreateCustomer::class, Id::generate());

    Livewire::test('pages::customers.index')
        ->call('openCreate')
        ->set('name', 'Bad Phone')
        ->set('phone', 'not-a-phone')
        ->call('create')
        ->assertHasErrors(['phone']);

    $fake->assertNotDispatched(CreateCustomer::class);
});

it('dispatches ListCustomers with the search term when typing in the search box', function () {
    $fake = Mediator::fake(ListCustomers::class, fakeListPaginator());

    Livewire::test('pages::customers.index')
        ->set('search', 'alice');

    $fake->assertDispatched(
        ListCustomers::class,
        fn (ListCustomers $cmd) => $cmd->search === 'alice'
            && $cmd->businessId->toString() === $this->business->ulid
    );
});

it('redirects guests to /login', function () {
    auth()->logout();

    $this->get('/customers')
        ->assertRedirect('/login');
});

it('returns 403 for an authenticated user without a business', function () {
    $loner = User::create([
        'name' => 'Loner',
        'email' => 'loner@x.com',
        'workos_id' => 'wl-1',
        'avatar' => '',
        'role' => UserRole::SuperAdmin->value,
    ]);

    $this->actingAs($loner)
        ->get('/customers')
        ->assertForbidden();
});

it('renders the page for a business member', function () {
    Customer::factory()->for($this->business)->count(2)->create();

    $this->get('/customers')
        ->assertOk()
        ->assertSee('Customers');
});
