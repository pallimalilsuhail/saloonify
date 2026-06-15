<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Shared\ValueObjects\Email;
use Shared\ValueObjects\Id;
use Shared\ValueObjects\PhoneNumber;

it('extracts a required Id from input via asId', function () {
    $id = Id::generate();
    $request = Request::create('/', 'POST', ['business_id' => $id->toString()]);

    expect($request->asId('business_id'))->toBeInstanceOf(Id::class)
        ->and($request->asId('business_id')->toString())->toBe($id->toString());
});

it('returns null from asIdOrNull when missing or empty', function () {
    $request = Request::create('/', 'POST', ['business_id' => '']);

    expect($request->asIdOrNull('business_id'))->toBeNull()
        ->and($request->asIdOrNull('missing'))->toBeNull();
});

it('extracts an Id from a route parameter via asRouteId', function () {
    $id = Id::generate();
    $request = Request::create('/', 'GET');
    $request->setRouteResolver(function () use ($id, $request) {
        $route = new Route('GET', '/businesses/{business}', fn () => null);
        $route->bind($request);
        $route->setParameter('business', $id->toString());

        return $route;
    });

    expect($request->asRouteId('business')->toString())->toBe($id->toString());
});

it('extracts an Email via asEmail and returns null via asEmailOrNull when empty', function () {
    $request = Request::create('/', 'POST', ['email' => 'a@b.com', 'optional' => '']);

    expect($request->asEmail('email'))->toBeInstanceOf(Email::class)
        ->and($request->asEmail('email')->toString())->toBe('a@b.com')
        ->and($request->asEmailOrNull('optional'))->toBeNull()
        ->and($request->asEmailOrNull('missing'))->toBeNull();
});

it('extracts a PhoneNumber via asPhoneNumber', function () {
    $request = Request::create('/', 'POST', ['phone' => '+971501234567']);

    expect($request->asPhoneNumber('phone'))->toBeInstanceOf(PhoneNumber::class)
        ->and($request->asPhoneNumber('phone')->toE164())->toBe('+971501234567');
});

it('extracts strings via asString and asStringOrNull', function () {
    $request = Request::create('/', 'POST', ['name' => 'Suhail', 'empty' => '']);

    expect($request->asString('name'))->toBe('Suhail')
        ->and($request->asStringOrNull('empty'))->toBeNull()
        ->and($request->asStringOrNull('missing'))->toBeNull();
});
