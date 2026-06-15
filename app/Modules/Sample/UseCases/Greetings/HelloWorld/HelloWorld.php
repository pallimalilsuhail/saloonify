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
