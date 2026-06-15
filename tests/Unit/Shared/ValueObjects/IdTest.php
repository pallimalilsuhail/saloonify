<?php

declare(strict_types=1);

use Shared\ValueObjects\Id;

it('generates a valid ULID', function () {
    $id = Id::generate();

    expect((string) $id)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/');
});

it('round-trips through fromString', function () {
    $id = Id::generate();

    expect(Id::fromString($id->toString())->toString())->toBe($id->toString());
});

it('rejects invalid ULID strings', function () {
    Id::fromString('not-a-ulid');
})->throws(InvalidArgumentException::class);

it('compares equality of two Ids', function () {
    $a = Id::generate();
    $b = Id::fromString($a->toString());
    $c = Id::generate();

    expect($a->equals($b))->toBeTrue()
        ->and($a->equals($c))->toBeFalse();
});

it('serialises to JSON as the ULID string', function () {
    $id = Id::generate();

    expect(json_encode($id))->toBe('"'.$id->toString().'"');
});
