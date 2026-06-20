<?php

declare(strict_types=1);

namespace Shared\ValueObjects;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use InvalidArgumentException;
use JsonSerializable;
use Spatie\OpeningHours\Exceptions\Exception as SpatieOpeningHoursException;
use Spatie\OpeningHours\OpeningHours as SpatieOpeningHours;

/**
 * A week of opening hours, keyed by short day name (mon..sun). Each day holds one or more
 * non-overlapping same-day ranges (split shifts). Days that are absent are closed.
 */
final readonly class OpeningHours implements Castable, JsonSerializable
{
    private const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    /** Short day name => the full lowercase name spatie/opening-hours expects. */
    private const SPATIE_DAYS = [
        'mon' => 'monday',
        'tue' => 'tuesday',
        'wed' => 'wednesday',
        'thu' => 'thursday',
        'fri' => 'friday',
        'sat' => 'saturday',
        'sun' => 'sunday',
    ];

    /** @var array<string, list<TimeRange>> */
    public array $days;

    /**
     * @param  array<string, list<TimeRange>>  $days
     */
    public function __construct(array $days)
    {
        if ($days === []) {
            throw new InvalidArgumentException('Opening hours must cover at least one day');
        }

        foreach ($days as $day => $ranges) {
            if (! in_array($day, self::DAYS, true)) {
                throw new InvalidArgumentException("Invalid day: {$day}");
            }

            if ($ranges === []) {
                throw new InvalidArgumentException("Day {$day} must have at least one opening range");
            }
        }

        $this->guardAgainstOverlaps($days);

        $this->days = $days;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $data
     */
    public static function fromArray(array $data): self
    {
        $days = [];

        foreach ($data as $day => $ranges) {
            $days[(string) $day] = array_map(
                static fn (array $range): TimeRange => TimeRange::fromArray($range),
                array_values((array) $ranges),
            );
        }

        return new self($days);
    }

    /**
     * @return array<string, list<array<string, string>>>
     */
    public function toArray(): array
    {
        return array_map(
            static fn (array $ranges): array => array_map(
                static fn (TimeRange $r): array => $r->toArray(),
                $ranges,
            ),
            $this->days,
        );
    }

    /**
     * @return array<string, list<array<string, string>>>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Delegate overlap detection to spatie/opening-hours, which throws on overlapping ranges.
     *
     * @param  array<string, list<TimeRange>>  $days
     */
    private function guardAgainstOverlaps(array $days): void
    {
        $spec = [];

        foreach ($days as $day => $ranges) {
            $spec[self::SPATIE_DAYS[$day]] = array_map(
                static fn (TimeRange $r): string => $r->toRangeString(),
                $ranges,
            );
        }

        try {
            SpatieOpeningHours::create($spec);
        } catch (SpatieOpeningHoursException $e) {
            throw new InvalidArgumentException($e->getMessage(), previous: $e);
        }
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
