<?php

declare(strict_types=1);

use Shared\ValueObjects\PhoneNumber;

it('parses a valid UAE phone number in E164 format', function () {
    $phone = new PhoneNumber('+971501234567');

    expect($phone->toE164())->toBe('+971501234567')
        ->and($phone->getCountryCode())->toBe(971)
        ->and($phone->getRegionCode())->toBe('AE');
});

it('parses a national-format number using the default region', function () {
    $phone = new PhoneNumber('0501234567');

    expect($phone->toE164())->toBe('+971501234567');
});

it('rejects an invalid phone number', function () {
    new PhoneNumber('not-a-phone');
})->throws(InvalidArgumentException::class);

it('returns null from tryFrom when given an invalid value', function () {
    expect(PhoneNumber::tryFrom('not-a-phone'))->toBeNull();
});

it('rejects E164 input that does not start with a plus', function () {
    PhoneNumber::fromE164('971501234567');
})->throws(InvalidArgumentException::class);

it('compares equality on the E164 representation', function () {
    expect((new PhoneNumber('+971501234567'))
        ->equals(new PhoneNumber('0501234567')))
        ->toBeTrue();
});

it('flags UAE mobile numbers as mobile', function () {
    expect((new PhoneNumber('+971501234567'))->isMobile())->toBeTrue();
});
