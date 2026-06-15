# Multi-tenancy

Tenant scoping is enforced **per query**, not via Eloquent global scopes.

Every UseCase that loads tenant-owned data MUST tap `BelongsToBusiness` on the query. Forgetting it leaks records across businesses — there is no safety net.

## File

`app/Modules/Common/QueryFilters/BelongsToBusiness.php`

## Why a query filter, not a global scope

Global scopes are implicit. They auto-apply to every query on a model — easy to forget you're inside one, and bypassing them (`withoutGlobalScopes()`) accidentally drops the guard.

A `tap()` filter is **explicit**. The call appears at every read site, so reviewers can see the tenant guard is live. Cross-tenant access requires deliberately omitting the tap, which is a visible decision.

## Usage

```php
use App\Modules\Common\QueryFilters\BelongsToBusiness;

$customers = Customer::query()
    ->tap(new BelongsToBusiness($businessId))
    ->where('email', $email)
    ->get();
```

`$businessId` is a `Shared\ValueObjects\Id` (ULID). The filter resolves the business by ULID via subquery — callers never need the internal numeric `id`.

## In a Mediator handler

```php
final readonly class GetCustomerHandler implements RequestHandler
{
    public function handle(Request $request): Customer
    {
        return Customer::query()
            ->tap(new BelongsToBusiness($request->businessId))
            ->where('ulid', $request->customerId->toString())
            ->firstOrFail();
    }
}
```

The Request DTO carries `$businessId` (read from the route via `FormRequest::getBusinessId()`). The handler taps it on every Eloquent query.

## When NOT to apply

- **Super admin queries** that intentionally span all businesses (e.g. `ListBusinesses`). These don't pass through `BelongsToBusiness`.
- **System-level operations** (e.g. queue worker, console command) where there is no tenant context.

For super admin routes, a separate guard (the `super_admin` middleware) gates access.

## Testing

```php
use App\Modules\Common\QueryFilters\BelongsToBusiness;

it('refuses cross-tenant reads', function () {
    $a = Business::factory()->create();
    $b = Business::factory()->create();
    $customerInB = Customer::factory()->for($b)->create();

    $result = Customer::query()
        ->tap(new BelongsToBusiness(Id::fromString($a->ulid)))
        ->where('ulid', $customerInB->ulid)
        ->first();

    expect($result)->toBeNull();
});
```

`tests/Feature/Common/BelongsToBusinessTest.php` covers the filter against the `users` table (which has `business_id`). Add equivalent tests for each new tenant model as it lands.

## Performance note

The subquery resolves the business by ULID on every read. If profiling shows it's hot, two options:

1. Resolve the numeric `business_id` once at the controller boundary and pass it down. Trade-off: handlers now know the internal id.
2. Cache the ULID → id mapping in Redis. Trade-off: cache invalidation on business deletion.

Don't optimise pre-emptively — measure first.
