<?php

declare(strict_types=1);

use Shared\ValueObjects\Address;

test('address round-trips from + to array', function (): void {
    $address = Address::fromArray([
        'street' => '1 St',
        'city' => 'Dubai',
        'emirate' => 'Dubai',
        'country' => 'ae',
    ]);

    expect($address->country)->toBe('AE')
        ->and($address->toArray())->toBe([
            'street' => '1 St',
            'city' => 'Dubai',
            'emirate' => 'Dubai',
            'country' => 'AE',
        ]);
});

test('address rejects an empty street', function (): void {
    new Address('', 'Dubai', 'Dubai', 'AE');
})->throws(InvalidArgumentException::class);

test('address rejects a non 2-letter country', function (): void {
    new Address('1 St', 'Dubai', 'Dubai', 'UAE');
})->throws(InvalidArgumentException::class);
