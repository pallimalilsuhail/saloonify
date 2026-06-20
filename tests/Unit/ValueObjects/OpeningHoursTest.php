<?php

declare(strict_types=1);

use Shared\ValueObjects\OpeningHours;
use Shared\ValueObjects\TimeRange;

test('valid time range round-trips', function (): void {
    $range = new TimeRange('09:00', '21:00');

    expect($range->toArray())->toBe(['open' => '09:00', 'close' => '21:00'])
        ->and($range->toRangeString())->toBe('09:00-21:00');
});

test('opening time must be before closing time', function (): void {
    new TimeRange('21:00', '09:00');
})->throws(InvalidArgumentException::class);

test('rejects a malformed time', function (): void {
    new TimeRange('9am', '21:00');
})->throws(InvalidArgumentException::class);

test('OpeningHours builds from + serializes to an array with split shifts', function (): void {
    $hours = OpeningHours::fromArray([
        'mon' => [['open' => '09:00', 'close' => '13:00'], ['open' => '16:00', 'close' => '21:00']],
        'tue' => [['open' => '10:00', 'close' => '20:00']],
    ]);

    expect($hours->toArray())->toBe([
        'mon' => [['open' => '09:00', 'close' => '13:00'], ['open' => '16:00', 'close' => '21:00']],
        'tue' => [['open' => '10:00', 'close' => '20:00']],
    ]);
});

test('OpeningHours rejects overlapping ranges within a day', function (): void {
    OpeningHours::fromArray([
        'mon' => [['open' => '09:00', 'close' => '13:00'], ['open' => '12:00', 'close' => '18:00']],
    ]);
})->throws(InvalidArgumentException::class);

test('OpeningHours rejects an invalid day', function (): void {
    OpeningHours::fromArray(['funday' => [['open' => '09:00', 'close' => '21:00']]]);
})->throws(InvalidArgumentException::class);

test('OpeningHours requires at least one day', function (): void {
    OpeningHours::fromArray([]);
})->throws(InvalidArgumentException::class);

test('OpeningHours requires at least one range per day', function (): void {
    OpeningHours::fromArray(['mon' => []]);
})->throws(InvalidArgumentException::class);
