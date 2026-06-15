<?php

declare(strict_types=1);

namespace Shared\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

final readonly class Email implements JsonSerializable, Stringable
{
    private string $email;

    public function __construct(string $email)
    {
        $normalized = strtolower(trim($email));

        if ($normalized === '') {
            throw new InvalidArgumentException('Email cannot be empty');
        }

        if (! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid email address: {$email}");
        }

        $this->email = $normalized;
    }

    public static function fromNullable(?string $email): ?self
    {
        if ($email === null || trim($email) === '') {
            return null;
        }

        return new self($email);
    }

    public static function tryFrom(string $email): ?self
    {
        try {
            return new self($email);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public function toString(): string
    {
        return $this->email;
    }

    public function equals(self $other): bool
    {
        return $this->email === $other->email;
    }

    public function matches(string $email): bool
    {
        return $this->email === strtolower(trim($email));
    }

    public function __toString(): string
    {
        return $this->email;
    }

    public function jsonSerialize(): string
    {
        return $this->email;
    }
}
