<?php

declare(strict_types=1);

namespace Shared\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * A time of day in 24h HH:MM form (00:00–23:59).
 */
final readonly class Time implements JsonSerializable, Stringable
{
    public int $hour;

    public int $minute;

    public function __construct(string $value)
    {
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $value, $m) !== 1) {
            throw new InvalidArgumentException("Invalid time: {$value} (expected HH:MM)");
        }

        $this->hour = (int) $m[1];
        $this->minute = (int) $m[2];
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /**
     * Minutes since midnight — the canonical value for ordering/comparison.
     */
    public function minutesSinceMidnight(): int
    {
        return $this->hour * 60 + $this->minute;
    }

    public function isBefore(self $other): bool
    {
        return $this->minutesSinceMidnight() < $other->minutesSinceMidnight();
    }

    public function equals(self $other): bool
    {
        return $this->minutesSinceMidnight() === $other->minutesSinceMidnight();
    }

    public function toString(): string
    {
        return sprintf('%02d:%02d', $this->hour, $this->minute);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function jsonSerialize(): string
    {
        return $this->toString();
    }
}
