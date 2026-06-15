<?php

declare(strict_types=1);

namespace App\Modules\Customers\UseCases\UpdateCustomer;

use App\Modules\Common\Services\EventCollector;
use App\Modules\Customers\Events\CustomerUpdated;
use AvoqadoDev\UseCase\BusinessRules\Contracts\GuardsRules;
use AvoqadoDev\UseCase\Contracts\Request;
use AvoqadoDev\UseCase\Contracts\RequestHandler;
use Illuminate\Pipeline\Pipeline;
use Shared\ValueObjects\Id;

final readonly class UpdateCustomerHandler implements RequestHandler
{
    public function __construct(
        private GuardsRules $guards,
    ) {}

    /**
     * @param  UpdateCustomer  $request
     */
    public function handle(Request $request): Id
    {
        $passable = new UpdateCustomerPassable(
            request: $request,
            customer: null,
            eventCollector: new EventCollector,
            guardsRules: $this->guards,
        );

        return app(Pipeline::class)
            ->send($passable)
            ->through([
                Pipes\LoadCustomer::class,
                Pipes\ValidateRules::class,
                Pipes\UpdateBasicInfo::class,
                Pipes\UpdateContact::class,
            ])
            ->then(function (UpdateCustomerPassable $passable): Id {
                if ($passable->changes !== []) {
                    $passable->eventCollector->collect(new CustomerUpdated(
                        customerId: $passable->request->customerId,
                        businessId: $passable->request->businessId,
                        changes: $passable->changes,
                    ));
                }

                $passable->eventCollector->dispatchAll();

                return $passable->request->customerId;
            });
    }
}
