<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\Extractors\IdExtractor;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Shared\ValueObjects\Email;
use Shared\ValueObjects\Id;
use Shared\ValueObjects\PhoneNumber;

/**
 * Request mixin providing type-safe data extraction methods.
 *
 * @mixin Request
 */
final class RequestMixin
{
    /**
     * @return Closure(string): Id
     */
    public function asId(): Closure
    {
        return fn (string $attribute): Id => Id::fromString(
            (string) $this->input($attribute)
        );
    }

    /**
     * @return Closure(string): ?Id
     */
    public function asIdOrNull(): Closure
    {
        return function (string $attribute): ?Id {
            $value = $this->input($attribute);

            return ($value === null || $value === '')
                ? null
                : Id::fromString((string) $value);
        };
    }

    /**
     * @return Closure(string): IdExtractor
     */
    public function extractId(): Closure
    {
        return fn (string $attribute): IdExtractor => new IdExtractor($this, $attribute);
    }

    /**
     * @return Closure(string): Id
     */
    public function asRouteId(): Closure
    {
        return function (string $parameter): Id {
            /** @var string $value */
            $value = $this->route($parameter);

            return Id::fromString($value);
        };
    }

    /**
     * @return Closure(string): string
     */
    public function asString(): Closure
    {
        return fn (string $attribute): string => $this->string($attribute)->toString();
    }

    /**
     * @return Closure(string): ?string
     */
    public function asStringOrNull(): Closure
    {
        return function (string $attribute): ?string {
            $value = $this->input($attribute);

            return ($value === null || $value === '')
                ? null
                : (string) $value;
        };
    }

    /**
     * @return Closure(string): Email
     */
    public function asEmail(): Closure
    {
        return fn (string $attribute): Email => new Email(
            (string) $this->input($attribute)
        );
    }

    /**
     * @return Closure(string): ?Email
     */
    public function asEmailOrNull(): Closure
    {
        return fn (string $attribute): ?Email => Email::fromNullable(
            $this->input($attribute)
        );
    }

    /**
     * @return Closure(string): PhoneNumber
     */
    public function asPhoneNumber(): Closure
    {
        return fn (string $attribute): PhoneNumber => new PhoneNumber(
            (string) $this->input($attribute)
        );
    }

    /**
     * @return Closure(string): ?PhoneNumber
     */
    public function asPhoneNumberOrNull(): Closure
    {
        return fn (string $attribute): ?PhoneNumber => PhoneNumber::tryFrom(
            (string) ($this->input($attribute) ?? '')
        );
    }

    /**
     * @return Closure(string): CarbonImmutable
     */
    public function asCarbonImmutable(): Closure
    {
        return fn (string $attribute): CarbonImmutable => CarbonImmutable::parse(
            (string) $this->input($attribute)
        );
    }

    /**
     * @return Closure(string): ?CarbonImmutable
     */
    public function asCarbonImmutableOrNull(): Closure
    {
        return function (string $attribute): ?CarbonImmutable {
            $value = $this->input($attribute);

            return ($value === null || $value === '')
                ? null
                : CarbonImmutable::parse((string) $value);
        };
    }
}
