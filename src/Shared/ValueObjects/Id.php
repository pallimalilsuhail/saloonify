<?php

declare(strict_types=1);

namespace Shared\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;
use JsonSerializable;
use Stringable;
use Symfony\Component\Uid\Ulid;

readonly class Id implements JsonSerializable, Stringable
{
    private Ulid $value;

    private function __construct(Ulid $value)
    {
        $this->value = $value;
    }

    public static function generate(): self
    {
        return new self(new Ulid);
    }

    public static function fromString(string $value): self
    {
        if (! Ulid::isValid($value)) {
            throw new InvalidArgumentException("Invalid ULID: {$value}");
        }

        return new self(new Ulid($value));
    }

    public static function fromUlid(Ulid $ulid): self
    {
        return new self($ulid);
    }

    public function toString(): string
    {
        return (string) $this->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }

    public function toUlid(): Ulid
    {
        return $this->value;
    }

    public function getTimestamp(): DateTimeImmutable
    {
        return $this->value->getDateTime();
    }

    public function equals(self $other): bool
    {
        return $this->value->equals($other->value);
    }

    public function compare(self $other): int
    {
        return $this->value->compare($other->value);
    }

    public function jsonSerialize(): string
    {
        return (string) $this->value;
    }
}
