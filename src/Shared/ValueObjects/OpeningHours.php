<?php

declare(strict_types=1);

namespace Shared\ValueObjects;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use InvalidArgumentException;
use JsonSerializable;

/**
 * A week of opening hours, keyed by day. Days that are absent are closed.
 */
final readonly class OpeningHours implements Castable, JsonSerializable
{
    /** @var array<string, OpeningHour> */
    public array $days;

    /**
     * @param  array<string, OpeningHour>  $days
     */
    public function __construct(array $days)
    {
        if ($days === []) {
            throw new InvalidArgumentException('Opening hours must cover at least one day');
        }

        $this->days = $days;
    }

    /**
     * @param  array<string, array<string, mixed>>  $data
     */
    public static function fromArray(array $data): self
    {
        $days = [];

        foreach ($data as $day => $hours) {
            $days[$day] = new OpeningHour(
                day: (string) $day,
                open: (string) ($hours['open'] ?? ''),
                close: (string) ($hours['close'] ?? ''),
            );
        }

        return new self($days);
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function toArray(): array
    {
        return array_map(static fn (OpeningHour $h): array => $h->toArray(), $this->days);
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public static function castUsing(array $arguments): CastsAttributes
    {
        return new class implements CastsAttributes
        {
            public function get($model, string $key, $value, array $attributes): ?OpeningHours
            {
                return $value === null ? null : OpeningHours::fromArray(json_decode($value, true));
            }

            public function set($model, string $key, $value, array $attributes): ?string
            {
                if ($value === null) {
                    return null;
                }

                $hours = $value instanceof OpeningHours ? $value : OpeningHours::fromArray((array) $value);

                return json_encode($hours->toArray());
            }
        };
    }
}
