<?php

declare(strict_types=1);

use Shared\ValueObjects\OpeningHour;
use Shared\ValueObjects\OpeningHours;

test('valid opening hour round-trips', function (): void {
    $hour = new OpeningHour('mon', '09:00', '21:00');

    expect($hour->toArray())->toBe(['open' => '09:00', 'close' => '21:00']);
});

test('opening time must be before closing time', function (): void {
    new OpeningHour('mon', '21:00', '09:00');
})->throws(InvalidArgumentException::class);

test('rejects an invalid day', function (): void {
    new OpeningHour('funday', '09:00', '21:00');
})->throws(InvalidArgumentException::class);

test('rejects a malformed time', function (): void {
    new OpeningHour('mon', '9am', '21:00');
})->throws(InvalidArgumentException::class);

test('OpeningHours builds from + serializes to an array', function (): void {
    $hours = OpeningHours::fromArray([
        'mon' => ['open' => '09:00', 'close' => '21:00'],
        'tue' => ['open' => '10:00', 'close' => '20:00'],
    ]);

    expect($hours->toArray())->toBe([
        'mon' => ['open' => '09:00', 'close' => '21:00'],
        'tue' => ['open' => '10:00', 'close' => '20:00'],
    ]);
});

test('OpeningHours requires at least one day', function (): void {
    OpeningHours::fromArray([]);
})->throws(InvalidArgumentException::class);
