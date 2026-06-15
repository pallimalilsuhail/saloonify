<?php

declare(strict_types=1);

namespace App\Modules\Customers\UseCases\CreateCustomer;

use App\Models\User;
use App\Modules\Businesses\Models\Business;
use App\Modules\Customers\BusinessRules\EmailUniquePerBusiness;
use App\Modules\Customers\BusinessRules\PhoneUniquePerBusiness;
use App\Modules\Customers\Events\CustomerCreated;
use App\Modules\Customers\Models\Customer;
use AvoqadoDev\UseCase\BusinessRules\Contracts\GuardsRules;
use AvoqadoDev\UseCase\Contracts\Request;
use AvoqadoDev\UseCase\Contracts\RequestHandler;
use Illuminate\Support\Facades\Event;
use Shared\ValueObjects\Email;
use Shared\ValueObjects\Id;

final readonly class CreateCustomerHandler implements RequestHandler
{
    public function __construct(private GuardsRules $guards) {}

    /**
     * @param  CreateCustomer  $request
     */
    public function handle(Request $request): Id
    {
        $rules = [new PhoneUniquePerBusiness($request->businessId, $request->phone)];

        if ($request->email instanceof Email) {
            $rules[] = new EmailUniquePerBusiness($request->businessId, $request->email);
        }

        $this->guards->guard(...$rules);

        $business = Business::query()
            ->where('ulid', $request->businessId->toString())
            ->firstOrFail();

        $createdById = null;

        if ($request->createdById instanceof Id) {
            $createdById = User::query()
                ->where('ulid', $request->createdById->toString())
                ->value('id');
        }

        $customer = Customer::create([
            'business_id' => $business->id,
            'name' => $request->name,
            'phone' => $request->phone->toE164(),
            'email' => $request->email?->toString(),
            'created_by_id' => $createdById,
        ]);

        $customerId = Id::fromString($customer->ulid);

        Event::dispatch(new CustomerCreated(
            customerId: $customerId,
            businessId: $request->businessId,
            createdById: $request->createdById,
        ));

        return $customerId;
    }
}
