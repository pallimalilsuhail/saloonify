<?php

declare(strict_types=1);

namespace Shared\Enums;

use BackedEnum;
use UnitEnum;
use ValueError;

/**
 * @mixin BackedEnum|UnitEnum
 */
trait EnumHelper
{
    /**
     * @return array<int, mixed>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_column(self::cases(), 'name');
    }

    public static function fromName(string $name): self
    {
        /** @var self */
        return current(
            array_filter(self::cases(), static fn (UnitEnum $enum): bool => $enum->name === $name)
                ?: throw new ValueError(sprintf('"%s" is not a valid backing name for enum "%s".', $name, self::class))
        );
    }

    public function is(self $other): bool
    {
        return $other === $this;
    }

    public function in(self ...$others): bool
    {
        return in_array($this, $others, true);
    }
}
