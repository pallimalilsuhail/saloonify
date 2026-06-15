<?php

declare(strict_types=1);

use Shared\ValueObjects\Email;

it('normalises and stores a valid email', function () {
    $email = new Email('  Foo@Example.COM ');

    expect($email->toString())->toBe('foo@example.com');
});

it('rejects an empty email', function () {
    new Email('');
})->throws(InvalidArgumentException::class);

it('rejects an invalid email', function () {
    new Email('not-an-email');
})->throws(InvalidArgumentException::class);

it('returns null from fromNullable when given empty input', function () {
    expect(Email::fromNullable(null))->toBeNull()
        ->and(Email::fromNullable(''))->toBeNull()
        ->and(Email::fromNullable('   '))->toBeNull();
});

it('returns an Email from fromNullable when given a valid value', function () {
    expect(Email::fromNullable('hello@example.com'))
        ->toBeInstanceOf(Email::class);
});

it('compares equality case-insensitively', function () {
    expect((new Email('A@B.com'))->equals(new Email('a@b.com')))->toBeTrue();
});

it('matches a raw string after normalisation', function () {
    expect((new Email('a@b.com'))->matches('  A@B.COM '))->toBeTrue();
});
