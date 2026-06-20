<?php

declare(strict_types=1);

namespace Shared\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * A single same-day opening window (open before close; overnight ranges are not supported).
 */
final readonly class TimeRange implements JsonSerializable
{
    public Time $open;

    public Time $close;

    public function __construct(string $open, string $close)
    {
        $this->open = new Time($open);
        $this->close = new Time($close);

        if (! $this->open->isBefore($this->close)) {
            throw new InvalidArgumentException("Opening time {$open} must be before closing time {$close}");
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            open: (string) ($data['open'] ?? ''),
            close: (string) ($data['close'] ?? ''),
        );
    }

    /**
     * The "HH:MM-HH:MM" form spatie/opening-hours expects.
     */
    public function toRangeString(): string
    {
        return $this->open->toString().'-'.$this->close->toString();
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return ['open' => $this->open->toString(), 'close' => $this->close->toString()];
    }

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
