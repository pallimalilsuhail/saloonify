<?php

declare(strict_types=1);

use Shared\ValueObjects\Time;

test('valid time round-trips', function (): void {
    $time = new Time('09:05');

    expect($time->toString())->toBe('09:05')
        ->and($time->hour)->toBe(9)
        ->and($time->minute)->toBe(5)
        ->and($time->minutesSinceMidnight())->toBe(545);
});

test('rejects a malformed time', function (): void {
    new Time('9am');
})->throws(InvalidArgumentException::class);

test('rejects an out-of-range hour', function (): void {
    new Time('24:00');
})->throws(InvalidArgumentException::class);

test('compares two times', function (): void {
    $open = new Time('09:00');
    $close = new Time('21:00');

    expect($open->isBefore($close))->toBeTrue()
        ->and($close->isBefore($open))->toBeFalse()
        ->and($open->equals(new Time('09:00')))->toBeTrue();
});
