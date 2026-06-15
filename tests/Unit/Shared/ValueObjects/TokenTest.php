<?php

declare(strict_types=1);

use Shared\ValueObjects\Token;

it('generates a token whose URL-safe form decodes back to itself', function () {
    $token = Token::generate();
    $reparsed = Token::fromUrlSafe($token->urlSafe());

    expect($reparsed->hash())->toBe($token->hash());
});

it('produces a 64-char hex sha256 hash', function () {
    expect(Token::generate()->hash())->toMatch('/^[a-f0-9]{64}$/');
});

it('produces a URL-safe representation with no padding or unsafe chars', function () {
    $urlSafe = Token::generate()->urlSafe();

    expect($urlSafe)
        ->not->toContain('=')
        ->not->toContain('+')
        ->not->toContain('/');
});

it('equalsHash matches its own hash and rejects others', function () {
    $token = Token::generate();
    $other = Token::generate();

    expect($token->equalsHash($token->hash()))->toBeTrue()
        ->and($token->equalsHash($other->hash()))->toBeFalse();
});

it('rejects invalid URL-safe input', function () {
    Token::fromUrlSafe('not-a-real-token');
})->throws(InvalidArgumentException::class);

it('produces different tokens on subsequent generate calls', function () {
    expect(Token::generate()->hash())
        ->not->toBe(Token::generate()->hash());
});
