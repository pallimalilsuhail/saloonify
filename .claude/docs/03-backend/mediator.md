# Mediator / Use Cases

All business operations flow through Use Cases. Two files per operation: a **Request** (DTO of inputs, plus return type via `@implements Request<TResponse>`) and a **Handler** (`RequestHandler::handle()` that performs the work).

The Mediator resolves the handler by appending `Handler` to the Request class FQCN. Both must live in the same namespace.

Package: [`avoqado-dev/laravel-usecase`](https://github.com/avoqado-dev/laravel-usecase).

## Folder layout

```
app/Modules/{Module}/UseCases/{Entity}/{UseCaseName}/
  ├── {UseCaseName}.php          # Request
  └── {UseCaseName}Handler.php   # Handler
```

Example: `app/Modules/Customers/UseCases/Customers/CreateCustomer/{CreateCustomer,CreateCustomerHandler}.php`.

> The `php artisan make:usecase` stub generator currently emits `app/UseCases/{Module}/{Entity}/{Name}/`. We override that — write files by hand at the path above until the package supports a configurable path.

## Request — shape

```php
<?php

declare(strict_types=1);

namespace App\Modules\Sample\UseCases\Greetings\HelloWorld;

use AvoqadoDev\UseCase\Contracts\Request;

/**
 * @see HelloWorldHandler
 *
 * @implements Request<string>
 */
final readonly class HelloWorld implements Request
{
    public function __construct(
        public string $name,
    ) {}
}
```

Rules:

- `final readonly class`
- `declare(strict_types=1)`
- Constructor property promotion only — no setters
- `@see {HandlerClassName}` PHPDoc — IDE jump-to-handler
- `@implements Request<TResponse>` PHPDoc — return type generics for static analysis
- Use Value Objects for typed inputs (`Id`, `Email`, `PhoneNumber`) — no raw scalars at module boundary

## Handler — shape

```php
<?php

declare(strict_types=1);

namespace App\Modules\Sample\UseCases\Greetings\HelloWorld;

use AvoqadoDev\UseCase\Contracts\Request;
use AvoqadoDev\UseCase\Contracts\RequestHandler;

final readonly class HelloWorldHandler implements RequestHandler
{
    /**
     * @param HelloWorld $request
     */
    public function handle(Request $request): string
    {
        return "Hello, {$request->name}.";
    }
}
```

Rules:

- `final readonly class`
- Inject dependencies via constructor (e.g. `GuardsRules`, repositories, services)
- `@param {RequestClass} $request` PHPDoc on `handle()` — narrows the union type from the contract
- Always declare a concrete return type that matches `@implements Request<…>`

## Dispatching

From a controller:

```php
use AvoqadoDev\UseCase\Facades\Mediator;

$message = Mediator::dispatch(new HelloWorld(name: 'World'));
```

Controllers stay thin: validate via FormRequest, build the Request DTO, dispatch, return a response. **No business logic in controllers.**

## Business rules

Use guard rules for invariants — not FormRequest validation. FormRequest is for shape; guard rules are for domain rules (e.g. "phone number must be unique per business").

```php
use AvoqadoDev\UseCase\BusinessRules\Contracts\GuardsRules;

final readonly class CreateCustomerHandler implements RequestHandler
{
    public function __construct(private GuardsRules $guardsRules) {}

    public function handle(Request $request): Id
    {
        $this->guardsRules->guard(
            new PhoneUniquePerBusiness($request->businessId, $request->phone),
            new EmailUniquePerBusiness($request->businessId, $request->email),
        );
        // ... create customer
    }
}
```

`GuardsRules` is auto-bound by the package's service provider — just inject the contract.

A failed guard throws `BusinessRuleException`. The package's exception handler renders it as JSON 422 with `{message, errors, context}`.

## Middleware

Globally configured in `config/usecase.php`:

```php
'middleware' => [
    ReadFromWriteDatabase::class,
    LoggerMiddleware::class,
    WithAtomicLock::class,
    WithCache::class,
    WithDatabaseTransaction::class,
],
```

To opt a Request in to behavior, implement the matching contract:

| Contract | Behavior |
|---|---|
| `UsesDatabaseTransaction` | Wrap handler in DB transaction. Implement `transactionAttempts(): int` for retries. |
| `Cacheable` | Cache the result. |
| `UsesAtomicLock` | Acquire an atomic lock for the duration. |
| `ReadsFromWriteDatabase` | Force reads to the writer connection. |

Per-dispatch middleware: `Mediator::dispatch($req, $extraMiddleware1, $extraMiddleware2)`.

## Testing

Use `MediatorFake` to assert dispatch without running handlers:

```php
use AvoqadoDev\UseCase\Facades\Mediator;
use AvoqadoDev\UseCase\Testing\MediatorFake;

Mediator::fake();
// ... exercise code under test
Mediator::assertDispatched(CreateCustomer::class);
```

To exercise the real handler in a feature test, just dispatch normally (as in `tests/Feature/Sample/HelloWorldTest.php`).
