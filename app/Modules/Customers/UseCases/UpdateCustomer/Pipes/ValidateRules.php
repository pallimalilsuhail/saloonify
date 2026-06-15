<?php

declare(strict_types=1);

namespace App\Modules\Customers\UseCases\UpdateCustomer\Pipes;

use App\Modules\Customers\BusinessRules\EmailUniquePerBusiness;
use App\Modules\Customers\BusinessRules\PhoneUniquePerBusiness;
use App\Modules\Customers\UseCases\UpdateCustomer\UpdateCustomerPassable;
use Closure;
use Shared\ValueObjects\Email;
use Shared\ValueObjects\PhoneNumber;

final readonly class ValidateRules
{
    public function handle(UpdateCustomerPassable $passable, Closure $next): mixed
    {
        $request = $passable->request;
        $rules = [];

        if ($request->phone instanceof PhoneNumber && $request->phone->toE164() !== $passable->customer->phone) {
            $rules[] = new PhoneUniquePerBusiness(
                $request->businessId,
                $request->phone,
                $request->customerId,
            );
        }

        if ($request->setsEmail() && $request->email instanceof Email && $request->email->toString() !== $passable->customer->email) {
            $rules[] = new EmailUniquePerBusiness(
                $request->businessId,
                $request->email,
                $request->customerId,
            );
        }

        if ($rules !== []) {
            $passable->guardsRules->guard(...$rules);
        }

        return $next($passable);
    }
}
