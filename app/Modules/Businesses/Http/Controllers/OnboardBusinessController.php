<?php

declare(strict_types=1);

namespace App\Modules\Businesses\Http\Controllers;

use App\Modules\Businesses\Http\Requests\OnboardBusinessRequest;
use App\Modules\Businesses\UseCases\OnboardBusiness\OnboardBusiness;
use AvoqadoDev\UseCase\Facades\Mediator;
use Illuminate\Http\JsonResponse;

final class OnboardBusinessController
{
    public function __invoke(OnboardBusinessRequest $request): JsonResponse
    {
        $result = Mediator::dispatch(new OnboardBusiness(
            name: $request->name(),
            trn: $request->trn(),
            adminName: $request->adminName(),
            login: $request->login(),
            password: $request->password(),
        ));

        return response()->json([
            'business_id' => $result->businessId,
            'login' => $result->login,
        ], 201);
    }
}
