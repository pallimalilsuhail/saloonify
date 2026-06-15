<?php

declare(strict_types=1);

namespace App\Modules\Sample\UseCases\Greetings\HelloWorld;

use AvoqadoDev\UseCase\Contracts\Request;
use AvoqadoDev\UseCase\Contracts\RequestHandler;

final readonly class HelloWorldHandler implements RequestHandler
{
    /**
     * @param  HelloWorld  $request
     */
    public function handle(Request $request): string
    {
        return "Hello, {$request->name}.";
    }
}
