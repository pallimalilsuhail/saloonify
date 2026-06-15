<?php

declare(strict_types=1);

use App\Modules\Sample\UseCases\Greetings\HelloWorld\HelloWorld;
use AvoqadoDev\UseCase\Facades\Mediator;

it('dispatches the HelloWorld use case via the Mediator', function () {
    expect(Mediator::dispatch(new HelloWorld(name: 'Suhail')))
        ->toBe('Hello, Suhail.');
});

it('returns the dispatched message via the route', function () {
    $this->getJson('/api/hello-world?name=World')
        ->assertOk()
        ->assertExactJson(['message' => 'Hello, World.']);
});
