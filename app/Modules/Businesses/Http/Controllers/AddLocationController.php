<?php

declare(strict_types=1);

namespace App\Modules\Businesses\Http\Controllers;

use App\Modules\Businesses\Http\Requests\AddLocationRequest;
use App\Modules\Businesses\UseCases\AddLocation\AddLocation;
use AvoqadoDev\UseCase\Facades\Mediator;
use Illuminate\Http\JsonResponse;

final class AddLocationController
{
    public function __invoke(AddLocationRequest $request): JsonResponse
    {
        $result = Mediator::dispatch(new AddLocation(
            businessUlid: $request->businessUlid(),
            name: $request->locationName(),
            address: $request->address(),
            openingHours: $request->openingHours(),
        ));

        return response()->json([
            'location_id' => $result->locationId,
        ], 201);
    }
}
