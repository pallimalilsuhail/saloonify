<?php

declare(strict_types=1);

namespace Shared\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber as LibPhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;
use Stringable;

final readonly class PhoneNumber implements JsonSerializable, Stringable
{
    private LibPhoneNumber $phoneNumber;

    private PhoneNumberUtil $phoneUtil;

    /**
     * @throws InvalidArgumentException If the phone number is invalid
     */
    public function __construct(
        private string $number,
        private ?string $defaultRegion = 'AE',
    ) {
        $this->phoneUtil = PhoneNumberUtil::getInstance();

        try {
            $this->phoneNumber = $this->phoneUtil->parse($number, $this->defaultRegion);

            if (! $this->phoneUtil->isValidNumber($this->phoneNumber)) {
                throw new InvalidArgumentException("Invalid phone number: {$number}");
            }
        } catch (NumberParseException $e) {
            throw new InvalidArgumentException("Cannot parse phone number: {$number}. Error: ".$e->getMessage());
        }
    }

    public static function fromE164(string $e164): self
    {
        if (! str_starts_with($e164, '+')) {
            throw new InvalidArgumentException('E164 format must start with +');
        }

        return new self($e164, null);
    }

    public static function tryFrom(string $number, ?string $defaultRegion = 'AE'): ?self
    {
        try {
            return new self($number, $defaultRegion);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public function toE164(): string
    {
        return $this->phoneUtil->format($this->phoneNumber, PhoneNumberFormat::E164);
    }

    public function toInternational(): string
    {
        return $this->phoneUtil->format($this->phoneNumber, PhoneNumberFormat::INTERNATIONAL);
    }

    public function toNational(): string
    {
        return $this->phoneUtil->format($this->phoneNumber, PhoneNumberFormat::NATIONAL);
    }

    public function getCountryCode(): int
    {
        return $this->phoneNumber->getCountryCode();
    }

    public function getRegionCode(): string
    {
        return $this->phoneUtil->getRegionCodeForNumber($this->phoneNumber);
    }

    public function isMobile(): bool
    {
        $type = $this->phoneUtil->getNumberType($this->phoneNumber);

        return $type === PhoneNumberType::MOBILE
            || $type === PhoneNumberType::FIXED_LINE_OR_MOBILE;
    }

    public function equals(self $other): bool
    {
        return $this->toE164() === $other->toE164();
    }

    public function getOriginal(): string
    {
        return $this->number;
    }

    public function __toString(): string
    {
        return $this->toE164();
    }

    public function jsonSerialize(): string
    {
        return $this->toE164();
    }
}
