<?php

declare(strict_types=1);

namespace App\Modules\Sample\Http\Controllers;

use App\Modules\Sample\UseCases\Greetings\HelloWorld\HelloWorld;
use AvoqadoDev\UseCase\Facades\Mediator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HelloWorldController
{
    public function __invoke(Request $request): JsonResponse
    {
        $message = Mediator::dispatch(new HelloWorld(
            name: (string) $request->query('name', 'World'),
        ));

        return response()->json(['message' => $message]);
    }
}
