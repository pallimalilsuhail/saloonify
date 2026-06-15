<?php

declare(strict_types=1);

namespace Shared\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

final readonly class OpeningHour implements JsonSerializable
{
    private const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    public function __construct(
        public string $day,
        public string $open,
        public string $close,
    ) {
        if (! in_array($day, self::DAYS, true)) {
            throw new InvalidArgumentException("Invalid day: {$day}");
        }

        foreach (['open' => $open, 'close' => $close] as $field => $value) {
            if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) !== 1) {
                throw new InvalidArgumentException("Invalid {$field} time: {$value} (expected HH:MM)");
            }
        }

        if ($open >= $close) {
            throw new InvalidArgumentException("Opening time {$open} must be before closing time {$close}");
        }
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return ['open' => $this->open, 'close' => $this->close];
    }

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
