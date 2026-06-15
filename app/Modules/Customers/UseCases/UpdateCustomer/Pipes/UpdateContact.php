<?php

declare(strict_types=1);

namespace App\Modules\Customers\UseCases\UpdateCustomer\Pipes;

use App\Modules\Customers\UseCases\UpdateCustomer\UpdateCustomerPassable;
use Closure;
use Shared\ValueObjects\Email;
use Shared\ValueObjects\PhoneNumber;

final readonly class UpdateContact
{
    public function handle(UpdateCustomerPassable $passable, Closure $next): mixed
    {
        if (! $passable->request->hasContactChanges()) {
            return $next($passable);
        }

        $request = $passable->request;
        $customer = $passable->customer;
        $updateData = [];

        if ($request->phone instanceof PhoneNumber) {
            $newPhone = $request->phone->toE164();
            if ($newPhone !== $customer->phone) {
                $updateData['phone'] = $newPhone;
                $passable->recordChange('phone', $customer->phone, $newPhone);
            }
        }

        if ($request->setsEmail() && $request->email instanceof Email) {
            $newEmail = $request->email->toString();
            if ($newEmail !== $customer->email) {
                $updateData['email'] = $newEmail;
                $passable->recordChange('email', $customer->email, $newEmail);
            }
        } elseif ($request->clearsEmail() && $customer->email !== null) {
            $updateData['email'] = null;
            $passable->recordChange('email', $customer->email, null);
        }

        if ($updateData !== []) {
            $customer->update($updateData);
        }

        return $next($passable);
    }
}
